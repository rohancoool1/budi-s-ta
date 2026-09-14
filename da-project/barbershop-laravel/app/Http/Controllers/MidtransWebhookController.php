<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransGateway $midtrans,
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        abort_unless($this->midtrans->validSignature($payload), 403, 'Signature tidak valid.');

        $payment = Payment::query()->where('reference', $payload['order_id'] ?? '')->firstOrFail();
        abort_unless($payment->provider === 'midtrans' && $payment->method === 'qris', 422, 'Provider pembayaran tidak cocok.');
        abort_unless($this->amountMatches($payload['gross_amount'] ?? null, $payment->amount), 422, 'Nominal tidak cocok.');
        abort_if(isset($payload['currency']) && strtoupper((string) $payload['currency']) !== 'IDR', 422, 'Mata uang tidak cocok.');

        $providerReference = (string) ($payload['transaction_id'] ?? '');
        abort_if($payment->provider_reference && ! hash_equals($payment->provider_reference, $providerReference), 422, 'Referensi provider tidak cocok.');

        if (! $payment->provider_reference && $providerReference !== '') {
            $payment->update(['provider_reference' => $providerReference]);
        }

        $status = (string) ($payload['transaction_status'] ?? '');

        $captureAccepted = $status !== 'capture' || in_array(($payload['fraud_status'] ?? 'accept'), ['accept', null], true);

        if (in_array($status, ['settlement', 'capture'], true) && $captureAccepted) {
            $this->payments->markPaid($payment);
        } elseif ($status === 'expire') {
            $this->payments->expire($payment);
        } elseif (in_array($status, ['deny', 'cancel'], true)) {
            $this->payments->fail($payment, 'Pembayaran ditolak oleh Midtrans.');
        }

        return response()->json(['received' => true]);
    }

    private function amountMatches(mixed $grossAmount, int $expected): bool
    {
        $value = trim((string) $grossAmount);

        if (! preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', $value, $matches)) {
            return false;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[2] ?? '';

        return $whole === (string) $expected
            && ($fraction === '' || (int) $fraction === 0);
    }
}
