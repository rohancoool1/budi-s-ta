<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\AdminNotifier;
use App\Services\BookingAvailabilityService;
use App\Services\BookingTransactionService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingTransactionService $transactions,
        private readonly PaymentService $payments,
        private readonly AdminNotifier $notifier,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_type' => ['required', 'in:service,artist'],
            'artist_id' => ['nullable', 'required_if:booking_type,artist', Rule::exists('barbers', 'slug')->where('is_active', true)],
            'service_id' => ['required', Rule::exists('services', 'slug')->where('is_active', true)],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'payment_method' => ['required', 'in:cash'],
        ]);

        $artist = $data['booking_type'] === 'artist' ? ($data['artist_id'] ?? null) : null;
        $this->ensureFutureTime($data['appointment_date'], $data['appointment_time']);
        $this->payments->expireDuePayments();
        $expiryMinutes = config('payments.booking_cash_expiry_minutes');
        $expiresAt = now()->addMinutes($expiryMinutes);

        [$booking, $order] = DB::transaction(function () use ($data, $artist, $expiresAt): array {
            $slot = $this->availability->resolve(
                $data['service_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $artist,
                lock: true,
            );
            $booking = new Booking([
                'booking_type' => $data['booking_type'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'status' => 'pending',
                'hold_expires_at' => $expiresAt,
            ]);
            $this->availability->applyToBooking($booking, $slot)->save();
            $order = $this->transactions->syncBooking($booking, 'cash');

            return [$booking, $order];
        }, 3);

        try {
            $payment = $this->payments->createForOrder($order, 'cash', $expiresAt);
        } catch (Throwable $exception) {
            report($exception);
            $this->payments->cancelOrder($order, 'Pembuatan pembayaran booking gagal.');

            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran belum dapat dibuat. Silakan coba lagi.',
            ]);
        }

        $this->notifier->send(
            'booking_created',
            'Booking pelanggan baru',
            "{$booking->name} memesan {$booking->service->name} pada {$booking->starts_at->translatedFormat('d M Y, H:i')} bersama {$booking->barber->name}. Nomor antrean {$order->queue_code}.",
            route('admin.resources.edit', ['resource' => 'bookings', 'record' => $booking]),
        );

        return to_route('payments.show', $payment)
            ->with('success', 'Booking tersimpan. Bayar tunai di kasir agar booking dikonfirmasi.');
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_type' => ['required', 'in:service,artist'],
            'artist_id' => ['nullable', 'required_if:booking_type,artist', Rule::exists('barbers', 'slug')->where('is_active', true)],
            'service_id' => ['required', Rule::exists('services', 'slug')->where('is_active', true)],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
        ]);
        $this->ensureFutureTime($data['appointment_date'], $data['appointment_time']);
        $this->payments->expireDuePayments();
        $artist = $data['booking_type'] === 'artist' ? ($data['artist_id'] ?? null) : null;
        $slot = $this->availability->resolve(
            $data['service_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $artist,
        );

        return response()->json([
            'available' => true,
            'message' => "Slot tersedia sampai {$slot['ends_at']->format('H:i')}.",
            'barber' => $slot['barber']->name,
        ]);
    }

    private function ensureFutureTime(string $date, string $time): void
    {
        $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, config('app.timezone'));

        if ($startsAt->lte(now())) {
            throw ValidationException::withMessages([
                'appointment_time' => 'Waktu booking tidak boleh berada di masa lalu.',
            ]);
        }
    }
}
