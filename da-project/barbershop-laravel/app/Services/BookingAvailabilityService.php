<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BookingAvailabilityService
{
    /**
     * @return array{barber: Barber, service: Service, starts_at: CarbonImmutable, ends_at: CarbonImmutable, duration_minutes: int}
     */
    public function resolve(
        string $serviceSlug,
        string $date,
        string $time,
        ?string $barberSlug = null,
        ?int $ignoreBookingId = null,
        bool $lock = false,
        ?int $durationMinutes = null,
    ): array {
        $service = Service::query()->where('slug', $serviceSlug)->where('is_active', true)->first();

        if (! $service) {
            throw ValidationException::withMessages(['service_id' => 'Layanan tidak tersedia.']);
        }

        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$time,
            config('app.timezone'),
        );
        $durationMinutes ??= $service->duration_minutes;
        $endsAt = $startsAt->addMinutes($durationMinutes);
        $shopOpen = $this->timeOnDate($startsAt, config('barbershop.booking_open_time'));
        $lastStart = $this->timeOnDate($startsAt, config('barbershop.booking_last_start_time'));
        $shopClose = $this->timeOnDate($startsAt, config('barbershop.booking_close_time'));

        if ($startsAt->lt($shopOpen) || $startsAt->gt($lastStart)) {
            throw ValidationException::withMessages([
                'appointment_time' => 'Booking hanya tersedia pukul '.$shopOpen->format('H:i').'–'.$lastStart->format('H:i').'.',
            ]);
        }

        if ($endsAt->gt($shopClose)) {
            $latestStart = $shopClose->subMinutes($durationMinutes);

            throw ValidationException::withMessages([
                'appointment_time' => "Layanan {$service->name} berdurasi {$durationMinutes} menit. Waktu mulai paling akhir adalah {$latestStart->format('H:i')} agar selesai sebelum toko tutup.",
            ]);
        }

        $barbers = Barber::query()
            ->where('is_active', true)
            ->when($barberSlug, fn ($query) => $query->where('slug', $barberSlug))
            ->orderBy('sort_order')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        if ($barbers->isEmpty()) {
            throw ValidationException::withMessages([
                'artist_id' => $barberSlug
                    ? 'Capster yang dipilih sedang tidak tersedia.'
                    : 'Belum ada capster aktif yang dapat menerima booking.',
            ]);
        }

        $hasBarberOnShift = false;

        foreach ($barbers as $barber) {
            $barberStarts = $this->timeOnDate($startsAt, $barber->work_start_time ?: config('barbershop.booking_open_time'));
            $barberEnds = $this->timeOnDate($startsAt, $barber->work_end_time ?: config('barbershop.booking_close_time'));

            if ($startsAt->lt($barberStarts) || $endsAt->gt($barberEnds)) {
                continue;
            }

            $hasBarberOnShift = true;

            if (! $this->hasConflict($barber, $startsAt, $endsAt, $ignoreBookingId, $lock)) {
                return [
                    'barber' => $barber,
                    'service' => $service,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $durationMinutes,
                ];
            }
        }

        if (! $hasBarberOnShift) {
            throw ValidationException::withMessages([
                'appointment_time' => $barberSlug
                    ? 'Waktu tersebut berada di luar jam kerja capster yang dipilih.'
                    : 'Tidak ada capster yang bertugas sampai layanan selesai pada waktu tersebut.',
            ]);
        }

        throw ValidationException::withMessages([
            'appointment_time' => $barberSlug
                ? 'Slot ini sudah digunakan booking atau walk-in untuk capster tersebut. Silakan pilih waktu lain.'
                : 'Semua capster sudah terisi pada waktu tersebut. Silakan pilih waktu lain.',
        ]);
    }

    private function timeOnDate(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $date->toDateString().' '.substr($time, 0, 5),
            config('app.timezone'),
        );
    }

    public function applyToBooking(Booking $booking, array $slot): Booking
    {
        /** @var Barber $barber */
        $barber = $slot['barber'];
        /** @var Service $service */
        $service = $slot['service'];

        $booking->fill([
            'artist_id' => $barber->slug,
            'barber_id' => $barber->id,
            'service_id' => $service->slug,
            'service_catalog_id' => $service->id,
            'appointment_date' => $slot['starts_at']->toDateString(),
            'appointment_time' => $slot['starts_at']->format('H:i'),
            'starts_at' => $slot['starts_at'],
            'ends_at' => $slot['ends_at'],
            'duration_minutes' => $slot['duration_minutes'],
        ]);

        return $booking;
    }

    private function hasConflict(
        Barber $barber,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $ignoreBookingId,
        bool $lock,
    ): bool {
        $query = Order::query()
            ->whereIn('transaction_type', ['service', 'mixed'])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('service_starts_at')
            ->where('service_starts_at', '<', $endsAt)
            ->where('service_ends_at', '>', $startsAt)
            ->when($ignoreBookingId, fn ($query) => $query->where(function ($query) use ($ignoreBookingId): void {
                $query->whereNull('booking_id')->orWhere('booking_id', '!=', $ignoreBookingId);
            }))
            ->whereHas('items', fn ($query) => $query
                ->where('item_type', 'service')
                ->where('barber_id', $barber->id));

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() !== null;
    }
}
