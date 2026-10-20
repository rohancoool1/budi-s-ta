<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerVerificationService
{
    public function __construct(private readonly CustomerOtpSender $sender) {}

    /** Send each unverified channel independently, retaining useful errors. */
    public function sendMissing(Customer $customer): array
    {
        $errors = [];
        foreach (['email', 'phone'] as $channel) {
            if ($customer->fresh()->{$channel.'_verified_at'}) {
                continue;
            }
            try {
                $this->send($customer, $channel);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors());
            }
        }

        return $errors;
    }

    public function send(Customer $customer, string $channel): bool
    {
        abort_unless(in_array($channel, ['email', 'phone'], true), 404);
        if ($customer->fresh()->{$channel.'_verified_at'}) {
            return false;
        }
        try {
            $this->sender->assertConfigured($channel);
        } catch (Throwable $exception) {
            $this->deliveryFailure($customer, $channel, $exception);
        }

        $challenge = DB::transaction(function () use ($customer, $channel): ?array {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($customer->{$channel.'_verified_at'}) {
                return null;
            }
            $record = CustomerVerificationCode::query()
                ->where('customer_id', $customer->id)->where('channel', $channel)
                ->lockForUpdate()->first();
            $cooldown = (int) config('customer_verification.resend_seconds');
            if ($record && $record->issued_at->copy()->addSeconds($cooldown)->isFuture()) {
                throw ValidationException::withMessages([
                    $channel.'_code' => "Tunggu {$cooldown} detik sejak pengiriman terakhir sebelum meminta kode baru.",
                ]);
            }

            $destination = (string) $customer->{$channel};
            $keys = [
                'customer-otp:account:'.$customer->id.':'.$channel,
                'customer-otp:destination:'.$channel.':'.hash('sha256', $destination),
            ];
            foreach ($keys as $key) {
                if (RateLimiter::tooManyAttempts($key, (int) config('customer_verification.sends_per_hour'))) {
                    throw ValidationException::withMessages([
                        $channel.'_code' => 'Batas pengiriman kode tercapai. Coba lagi dalam satu jam.',
                    ]);
                }
            }
            foreach ($keys as $key) {
                RateLimiter::hit($key, 3600);
            }

            $code = (string) random_int(100000, 999999);
            while ($record?->code_hash && Hash::check($code, $record->code_hash)) {
                $code = (string) random_int(100000, 999999);
            }
            $generation = (string) Str::uuid();
            $record ??= new CustomerVerificationCode;
            $record->fill([
                'customer_id' => $customer->id,
                'channel' => $channel,
                'destination' => $destination,
                'generation' => $generation,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'issued_at' => now(),
                'expires_at' => now()->addMinutes((int) config('customer_verification.expires_minutes')),
                'sent_at' => null,
                'consumed_at' => null,
            ])->save();

            return ['id' => $record->id, 'generation' => $generation, 'destination' => $destination, 'code' => $code];
        }, 3);

        if (! $challenge) {
            return false;
        }

        try {
            // External delivery happens once, outside the retried database transaction.
            $this->sender->send($channel, $challenge['destination'], $challenge['code']);
        } catch (Throwable $exception) {
            CustomerVerificationCode::query()->whereKey($challenge['id'])
                ->where('generation', $challenge['generation'])
                ->update(['code_hash' => '', 'expires_at' => now()]);
            $this->deliveryFailure($customer, $channel, $exception);
        }

        CustomerVerificationCode::query()->whereKey($challenge['id'])
            ->where('generation', $challenge['generation'])->update(['sent_at' => now()]);

        return true;
    }

    public function verify(Customer $customer, string $channel, string $code): void
    {
        abort_unless(in_array($channel, ['email', 'phone'], true), 404);
        $error = DB::transaction(function () use ($customer, $channel, $code): ?string {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $record = CustomerVerificationCode::query()
                ->where('customer_id', $customer->id)->where('channel', $channel)
                ->lockForUpdate()->first();

            if ($customer->{$channel.'_verified_at'}) {
                return 'Kontak ini sudah terverifikasi. Kode tidak dapat digunakan kembali.';
            }
            if (! $record || ! $record->sent_at || $record->consumed_at
                || $record->destination !== (string) $customer->{$channel}) {
                return 'Tidak ada kode aktif untuk kontak ini. Silakan kirim kode terlebih dahulu.';
            }
            if ($record->expires_at->lte(now())) {
                return 'Kode sudah kedaluwarsa. Silakan minta kode baru.';
            }
            $maxAttempts = (int) config('customer_verification.max_attempts');
            if ($record->attempts >= $maxAttempts) {
                return 'Batas percobaan kode tercapai. Silakan minta kode baru.';
            }
            if (! Hash::check($code, $record->code_hash)) {
                $record->increment('attempts');

                return $record->attempts >= $maxAttempts
                    ? 'Batas percobaan kode tercapai. Silakan minta kode baru.'
                    : 'Kode tidak sesuai. Periksa kode yang dikirim ke kontak ini.';
            }

            $customer->{$channel.'_verified_at'} = now();
            $customer->save();
            $record->update(['consumed_at' => now(), 'code_hash' => '']);

            return null;
        }, 3);

        // Throw only after committing, so incorrect attempts cannot be rolled back.
        if ($error) {
            throw ValidationException::withMessages([$channel.'_code' => $error]);
        }

        // Keep the authenticated instance in sync with the locked database record.
        $customer->refresh();
    }

    private function deliveryFailure(Customer $customer, string $channel, Throwable $exception): never
    {
        // Transport exception messages may contain addresses, credentials or message bodies.
        Log::warning('Customer OTP delivery failed.', [
            'customer_id' => $customer->id, 'channel' => $channel, 'exception_type' => $exception::class,
        ]);
        $label = $channel === 'email' ? 'email' : 'WhatsApp';
        throw ValidationException::withMessages([
            $channel.'_code' => "Kode {$label} belum dapat dikirim. Akun tetap tersimpan. Coba kirim ulang atau hubungi admin.",
        ]);
    }
}
