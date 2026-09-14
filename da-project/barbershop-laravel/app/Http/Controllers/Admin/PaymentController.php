<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function confirmCash(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $payment = $this->payments->confirmCash($order, $request->user());

        if ($request->expectsJson()) {
            $order->refresh()->load('booking');

            return response()->json([
                'message' => "Pembayaran tunai transaksi #{$order->id} berhasil dikonfirmasi.",
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
                'booking' => $order->booking ? [
                    'id' => $order->booking->id,
                    'status' => $order->booking->status,
                ] : null,
                'payment' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                ],
            ]);
        }

        return to_route('admin.resources.edit', ['resource' => 'orders', 'record' => $order])
            ->with('success', "Pembayaran tunai transaksi #{$order->id} berhasil dikonfirmasi.");
    }
}
