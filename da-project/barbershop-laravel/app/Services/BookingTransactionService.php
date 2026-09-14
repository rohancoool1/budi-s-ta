<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingTransactionService
{
    public function __construct(private readonly QueueNumberService $queueNumbers) {}

    public function syncBooking(Booking $booking, string $paymentMethod = 'cash', ?User $cashier = null): Order
    {
        return DB::transaction(function () use ($booking, $paymentMethod, $cashier): Order {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $service = $booking->service
                ?? Service::query()->where('slug', $booking->service_id)->firstOrFail();
            $barber = $booking->barber
                ?? Barber::query()->where('slug', $booking->artist_id)->first();
            $status = match ($booking->status) {
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                default => 'pending',
            };

            $order = Order::query()->where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $order) {
                $order = Order::create([
                    'customer_name' => $booking->name,
                    'phone' => $booking->phone,
                    'email' => null,
                    'address' => null,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'channel' => 'booking',
                    'transaction_type' => 'service',
                    'service_starts_at' => $booking->starts_at,
                    'service_ends_at' => $booking->ends_at,
                    'booking_id' => $booking->id,
                    'cashier_id' => $cashier?->id,
                    'subtotal' => $service->price,
                    'discount' => 0,
                    'total' => $service->price,
                    'status' => $status,
                    'notes' => "Transaksi otomatis untuk booking #{$booking->id}.",
                ]);
            } else {
                $orderData = [
                    'customer_name' => $booking->name,
                    'phone' => $booking->phone,
                    'channel' => 'booking',
                    'transaction_type' => 'service',
                    'service_starts_at' => $booking->starts_at,
                    'service_ends_at' => $booking->ends_at,
                    'cashier_id' => $cashier?->id ?? $order->cashier_id,
                    'status' => $status,
                ];

                if (! $order->payments()->exists()) {
                    $orderData['subtotal'] = $service->price;
                    $orderData['total'] = max(0, $service->price - $order->discount);
                }

                $order->update($orderData);
            }

            $itemIdentity = [
                'item_type' => 'service',
                'product_id' => null,
                'service_id' => $service->id,
                'barber_id' => $barber?->id,
            ];
            $itemFinancials = [
                'product_name' => $service->name,
                'unit_price' => $service->price,
                'quantity' => 1,
                'line_total' => $service->price,
            ];
            $item = $order->items()->where('item_type', 'service')->first();

            if ($item) {
                $item->update($order->payments()->exists() ? $itemIdentity : [...$itemIdentity, ...$itemFinancials]);
            } else {
                $order->items()->create([...$itemIdentity, ...$itemFinancials]);
            }

            if ($booking->status !== 'cancelled') {
                $order = $this->queueNumbers->assign($order, $booking->appointment_date);
            }

            return $order->refresh();
        });
    }

    public function recordCompletedBooking(Booking $booking, ?User $cashier = null): Order
    {
        return $this->syncBooking($booking, 'cash', $cashier);
    }
}
