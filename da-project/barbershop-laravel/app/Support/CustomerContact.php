<?php

namespace App\Support;

use Illuminate\Http\Request;

class CustomerContact
{
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($phone));

        if (str_starts_with($phone, '08')) {
            return '+62'.substr($phone, 1);
        }
        if (str_starts_with($phone, '628')) {
            return '+'.$phone;
        }

        return $phone;
    }

    public static function normalizeRequest(Request $request): void
    {
        $normalized = [];
        if (is_string($request->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($request->input('email')));
        }
        if (is_string($request->input('phone'))) {
            $normalized['phone'] = self::normalizePhone($request->input('phone'));
        }
        $request->merge($normalized);
    }

    public static function phoneRules(): array
    {
        return ['required', 'string', 'regex:/\A\+[1-9][0-9]{7,14}\z/'];
    }
}
