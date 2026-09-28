<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                    'workflow_status' => $order->workflow_status,
                    'workflow_label' => $order->workflow_label,
                ],
                'booking' => $order->booking ? [
                    'id' => $order->booking->id,
                    'status' => $order->booking->status,
                    'workflow_label' => $order->booking->workflow_label,
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

    public function confirmDeposit(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $payment = $this->payments->confirmDeposit($order, $request->user());
        $order->refresh()->load('booking');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "DP 50% transaksi #{$order->id} berhasil dikonfirmasi.",
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'workflow_status' => $order->workflow_status,
                    'workflow_label' => $order->workflow_label,
                ],
                'booking' => $order->booking ? [
                    'id' => $order->booking->id,
                    'status' => $order->booking->status,
                    'workflow_label' => $order->booking->workflow_label,
                ] : null,
                'payment' => ['id' => $payment->id, 'status' => $payment->status],
            ]);
        }

        return back()->with('success', "DP 50% transaksi #{$order->id} berhasil dikonfirmasi dan jadwal telah dikunci.");
    }

    public function complete(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $order = DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()->with('booking')->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi hanya dapat diselesaikan setelah pembayaran dikonfirmasi lunas.',
                ]);
            }

            if ($lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi yang dibatalkan tidak dapat diselesaikan.',
                ]);
            }

            if ($lockedOrder->status !== 'completed') {
                $lockedOrder->update(['status' => 'completed']);
            }

            if ($lockedOrder->booking && $lockedOrder->booking->status !== 'completed') {
                $lockedOrder->booking->update(['status' => 'completed']);
            }

            return $lockedOrder->refresh()->load('booking');
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Transaksi #{$order->id} berhasil diselesaikan.",
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'workflow_status' => $order->workflow_status,
                    'workflow_label' => $order->workflow_label,
                ],
                'booking' => $order->booking ? [
                    'id' => $order->booking->id,
                    'status' => $order->booking->status,
                    'workflow_label' => $order->booking->workflow_label,
                ] : null,
            ]);
        }

        return back()->with('success', "Transaksi #{$order->id} berhasil diselesaikan.");
    }
}
