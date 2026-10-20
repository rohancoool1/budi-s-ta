<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerContactsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        if ($customer && ! $customer->hasVerifiedContacts()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verifikasi email dan nomor WhatsApp terlebih dahulu.',
                    'verification_url' => route('customer.verification.notice'),
                ], 403);
            }

            return to_route('customer.verification.notice')
                ->with('status', 'Verifikasi email dan nomor WhatsApp sebelum menggunakan akun pelanggan.');
        }

        return $next($request);
    }
}
