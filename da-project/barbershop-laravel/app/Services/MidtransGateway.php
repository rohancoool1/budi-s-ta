<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransGateway
{
    public function provision(Payment $payment): Payment
    {
        $serverKey = (string) config('payments.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $payment->loadMissing('order');
        $baseUrl = config('payments.midtrans.production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
        $expiryMinutes = max(1, (int) ceil(now()->diffInSeconds($payment->expires_at, false) / 60));
        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($baseUrl.'/v2/charge', [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $payment->reference,
                    'gross_amount' => $payment->amount,
                ],
                'item_details' => [[
                    'id' => 'order-'.$payment->order_id,
                    'price' => $payment->amount,
                    'quantity' => 1,
                    'name' => 'Transaksi HOMCUTS #'.$payment->order_id,
                ]],
                'customer_details' => [
                    'first_name' => $payment->order->customer_name,
                    'email' => $payment->order->email,
                    'phone' => $payment->order->phone,
                ],
                'qris' => [
                    'acquirer' => config('payments.midtrans.qris_acquirer'),
                ],
                'custom_expiry' => [
                    'expiry_duration' => $expiryMinutes,
                    'unit' => 'minute',
                ],
            ])
            ->throw()
            ->json();

        $actions = collect($response['actions'] ?? []);
        $qrAction = $actions->firstWhere('name', 'generate-qr-code-v2')
            ?? $actions->firstWhere('name', 'generate-qr-code');

        if (empty($response['transaction_id']) || empty($qrAction['url'])) {
            throw new RuntimeException('Respons Midtrans tidak memiliki referensi transaksi atau kode QRIS.');
        }

        $payment->update([
            'provider_reference' => $response['transaction_id'] ?? null,
            'qr_url' => $qrAction['url'] ?? null,
            'metadata' => [
                'provider_order_id' => $response['order_id'] ?? $payment->reference,
                'merchant_id' => $response['merchant_id'] ?? null,
                'acquirer' => $response['acquirer'] ?? null,
                'transaction_status' => $response['transaction_status'] ?? 'pending',
            ],
        ]);

        return $payment->refresh();
    }

    public function validSignature(array $payload): bool
    {
        $serverKey = (string) config('payments.midtrans.server_key');
        $provided = (string) ($payload['signature_key'] ?? '');

        if ($serverKey === '' || $provided === '') {
            return false;
        }

        $expected = hash('sha512',
            ($payload['order_id'] ?? '').
            ($payload['status_code'] ?? '').
            ($payload['gross_amount'] ?? '').
            $serverKey,
        );

        return hash_equals($expected, $provided);
    }
}
