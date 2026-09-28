<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class PaymentPolicy
{
    public const MIN_EXPIRY_MINUTES = 5;

    public const MAX_EXPIRY_MINUTES = 1440;

    public static function expiryMinutes(): int
    {
        $fallback = (int) config('payments.cash_expiry_minutes', 30);

        if (! Schema::hasTable('site_settings')) {
            return $fallback;
        }

        $value = SiteSetting::query()->where('key', 'payment_expiry_minutes')->value('value');

        return max(self::MIN_EXPIRY_MINUTES, min(self::MAX_EXPIRY_MINUTES, (int) ($value ?: $fallback)));
    }

    public static function depositAmount(int $total): int
    {
        return intdiv(max(0, $total) + 1, 2);
    }
}
