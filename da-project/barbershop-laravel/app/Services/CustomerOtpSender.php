<?php

namespace App\Services;

use App\Mail\CustomerVerificationMail;
use App\Support\CustomerContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class CustomerOtpSender
{
    public function assertConfigured(string $channel): void
    {
        abort_unless(in_array($channel, ['email', 'phone'], true), 404);
        if ($channel === 'email') {
            $mailer = config('customer_verification.email_mailer');
            // Never silently treat a log/array delivery as verification email.
            if (config("mail.mailers.{$mailer}.transport") !== 'smtp'
                || ! config("mail.mailers.{$mailer}.host") || ! config('mail.from.address')) {
                throw new RuntimeException('OTP email requires an SMTP mailer.');
            }

            return;
        }

        if (trim((string) config('customer_verification.fonnte_token')) === '') {
            throw new RuntimeException('OTP WhatsApp requires a Fonnte device token.');
        }
    }

    public function send(string $channel, string $destination, string $code): void
    {
        $this->assertConfigured($channel);
        $minutes = (int) config('customer_verification.expires_minutes');

        if ($channel === 'email') {
            Mail::mailer(config('customer_verification.email_mailer'))
                ->to($destination)->send(new CustomerVerificationMail($code, $minutes));

            return;
        }

        $phone = CustomerContact::normalizePhone($destination);
        if (! preg_match('/\A\+[1-9][0-9]{7,14}\z/', $phone)) {
            throw new RuntimeException('OTP WhatsApp requires a valid international phone number.');
        }

        // No retries: a timed-out request may already have queued the OTP.
        $response = Http::asForm()
            ->withHeaders(['Authorization' => trim((string) config('customer_verification.fonnte_token'))])
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(5)->timeout(15)
            ->post('https://api.fonnte.com/send', [
                'target' => substr($phone, 1),
                // The destination is already international; do not add a country code again.
                'countryCode' => '0',
                'message' => "Kode verifikasi nomor WhatsApp HOMCUTS Anda: {$code}. Berlaku {$minutes} menit. Jangan bagikan kode ini.",
                'connectOnly' => true,
            ]);

        // Queue acceptance is not proof of delivery or phone ownership.
        // Avoid HTTP exceptions containing a provider body, destination or OTP.
        $ids = $response->json('id');
        if (! $response->successful() || $response->json('status') !== true
            || ! is_array($ids) || ! array_is_list($ids) || count($ids) !== 1 || ! is_scalar($ids[0]) || (string) $ids[0] === '') {
            throw new RuntimeException('Fonnte did not accept the WhatsApp OTP request.');
        }
    }
}
