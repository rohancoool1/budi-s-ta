<?php

namespace App\Support;

use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ServiceSchedule
{
    public const MIN_DURATION = 5;

    public const MAX_DURATION = 240;

    public static function settings(): Collection
    {
        if (! Schema::hasTable('site_settings')) {
            return collect();
        }

        return SiteSetting::query()
            ->whereIn('key', ['service_duration_minutes', 'store_open_time', 'store_close_time'])
            ->pluck('value', 'key');
    }

    public static function durationMinutes(?Collection $settings = null): int
    {
        $fallback = (int) config('barbershop.service_duration_minutes', 45);
        $raw = $settings?->get('service_duration_minutes');

        if ($raw === null) {
            $raw = self::settings()->get('service_duration_minutes');
        }

        if (! is_numeric($raw)) {
            return $fallback;
        }

        $duration = (int) $raw;

        return $duration >= self::MIN_DURATION && $duration <= self::MAX_DURATION
            ? $duration
            : $fallback;
    }

    public static function openingTime(?Collection $settings = null): string
    {
        $settings ??= self::settings();

        return self::normalizeTime(
            $settings->get('store_open_time'),
            (string) config('barbershop.booking_open_time', '07:00'),
        );
    }

    public static function closingTime(?Collection $settings = null): string
    {
        $settings ??= self::settings();

        return self::normalizeTime(
            $settings->get('store_close_time'),
            (string) config('barbershop.booking_close_time', '22:00'),
        );
    }

    public static function latestStartTime(?Collection $settings = null): string
    {
        $settings ??= self::settings();
        $timezone = config('app.timezone');
        $latestStart = CarbonImmutable::createFromFormat(
            'H:i',
            self::closingTime($settings),
            $timezone,
        )->subMinutes(self::durationMinutes($settings));

        return $latestStart->format('H:i');
    }

    public static function operatingMinutes(?Collection $settings = null): int
    {
        $settings ??= self::settings();
        $timezone = config('app.timezone');
        $opening = CarbonImmutable::createFromFormat('H:i', self::openingTime($settings), $timezone);
        $closing = CarbonImmutable::createFromFormat('H:i', self::closingTime($settings), $timezone);

        return (int) $opening->diffInMinutes($closing, false);
    }

    private static function normalizeTime(mixed $value, string $fallback): string
    {
        $candidate = is_string($value) ? $value : $fallback;

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $candidate)) {
            $candidate = $fallback;
        }

        return substr($candidate, 0, 5);
    }
}
