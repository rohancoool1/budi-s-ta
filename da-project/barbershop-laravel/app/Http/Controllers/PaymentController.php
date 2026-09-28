<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Payment $payment): View
    {
        $this->expireIfNeeded($payment);
        $payment->refresh()->load(['order.booking.barber', 'order.booking.service', 'order.items']);

        return view('payments.show', [
            'payment' => $payment,
        ]);
    }

    public function status(Payment $payment): JsonResponse
    {
        $this->expireIfNeeded($payment);
        $payment->refresh()->load(['order.booking.barber', 'order.booking.service']);
        $booking = $payment->order->booking;

        return response()->json([
            'status' => $payment->status,
            'payment_status' => $payment->order->payment_status,
            'order_status' => $payment->order->status,
            'workflow_status' => $payment->order->workflow_status,
            'workflow_label' => $payment->order->workflow_label,
            'paid_amount' => $payment->order->paid_amount,
            'remaining_amount' => $payment->order->remaining_amount,
            'booking_status' => $payment->order->booking?->status,
            'booking' => $booking ? [
                'status' => $booking->status,
                'schedule' => $booking->starts_at?->translatedFormat('d M Y, H:i'),
                'end_time' => $booking->ends_at?->format('H:i'),
                'barber' => $booking->barber?->name ?? $booking->artist_id ?? 'Belum ditentukan',
                'service' => $booking->service?->name ?? $booking->service_id,
                'queue_code' => $payment->order->queue_code,
                'schedule_changed_at' => $booking->schedule_changed_at?->toIso8601String(),
            ] : null,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ]);
    }

    public function invoice(Payment $payment): Response
    {
        $this->expireIfNeeded($payment);
        $payment->refresh()->load([
            'order.booking.barber',
            'order.booking.service',
            'order.items.service',
            'order.items.barber',
        ]);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', public_path());

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('payments.invoice', [
            'payment' => $payment,
            'invoiceUrl' => route('payments.show', $payment),
        ])->render());
        $pdf->setPaper('a4');
        $pdf->render();

        $filename = 'invoice-homcuts-'.str_pad((string) $payment->order_id, 5, '0', STR_PAD_LEFT).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function expireIfNeeded(Payment $payment): void
    {
        if ($payment->status === 'pending' && $payment->expires_at?->isPast()) {
            $this->payments->expire($payment);
        }
    }
}
