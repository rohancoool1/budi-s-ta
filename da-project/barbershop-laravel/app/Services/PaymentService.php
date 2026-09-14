<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly AdminNotifier $notifier,
    ) {}

    public function createForOrder(Order $order, string $method, ?CarbonInterface $expiresAt = null): Payment
    {
        if ($method !== 'cash') {
            throw ValidationException::withMessages(['payment_method' => 'Saat ini pembayaran hanya tersedia secara tunai di kasir.']);
        }

        $payment = DB::transaction(function () use ($order, $method, $expiresAt): Payment {
            $order = $this->lockOrder($order);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['payment_method' => 'Transaksi yang dibatalkan tidak dapat dibayar.']);
            }

            if ($order->payment_status === 'paid') {
                $paidPayment = $order->payments()->where('status', 'paid')->latest('id')->first();

                if ($paidPayment) {
                    return $paidPayment;
                }

                throw ValidationException::withMessages(['payment_method' => 'Transaksi ini sudah tercatat lunas.']);
            }

            $existing = $order->payments()
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $provider = 'cash';

            if ($existing && $existing->method === $method && $existing->provider === $provider && $existing->amount === $order->total) {
                return $existing;
            }

            if ($existing) {
                $existing->update([
                    'status' => 'cancelled',
                    'metadata' => [...($existing->metadata ?? []), 'cancel_reason' => 'Diganti dengan percobaan pembayaran baru.'],
                ]);
            }

            $reference = (string) Str::uuid();
            $payment = $order->payments()->create([
                'reference' => $reference,
                'method' => $method,
                'provider' => $provider,
                'amount' => $order->total,
                'status' => 'pending',
                'qr_payload' => null,
                'expires_at' => $expiresAt,
                'metadata' => ['created_from' => $order->channel],
            ]);

            $order->update([
                'payment_method' => $method,
                'payment_status' => 'unpaid',
                'paid_at' => null,
            ]);

            return $payment;
        }, 3);

        return $payment->load('order');
    }

    public function markPaid(Payment $payment, ?User $confirmedBy = null): Payment
    {
        $result = DB::transaction(function () use ($payment, $confirmedBy): array {
            [$order, $payment] = $this->lockOrderAndPayment($payment);

            if ($payment->status === 'paid') {
                return ['payment' => $payment, 'notice' => null];
            }

            // A repeated callback must not revive a payment that has already
            // been placed in manual review or refunded.
            if (in_array($payment->status, ['review', 'refunded'], true)) {
                return ['payment' => $payment, 'notice' => null];
            }

            $late = $order->status === 'cancelled'
                || $payment->expires_at?->isPast()
                || in_array($payment->status, ['expired', 'failed', 'cancelled'], true);

            if ($late) {
                if ($order->payment_status !== 'paid') {
                    $this->cancelLockedOrder($order, 'Pembayaran diterima setelah batas waktu.');
                }
                $payment->update([
                    'status' => 'review',
                    'metadata' => [...($payment->metadata ?? []), 'late_payment_received_at' => now()->toIso8601String()],
                ]);

                return ['payment' => $payment, 'notice' => 'late'];
            }

            if ($payment->amount !== $order->total) {
                $this->cancelLockedOrder($order, 'Nominal pembayaran tidak lagi cocok dengan total transaksi.');
                $payment->update([
                    'status' => 'review',
                    'metadata' => [
                        ...($payment->metadata ?? []),
                        'amount_mismatch' => ['payment' => $payment->amount, 'order' => $order->total],
                    ],
                ]);

                return ['payment' => $payment, 'notice' => 'amount'];
            }

            $otherPaidPayment = $order->payments()
                ->whereKeyNot($payment->id)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->first();

            if ($order->payment_status === 'paid' || $otherPaidPayment) {
                $payment->update([
                    'status' => 'review',
                    'metadata' => [...($payment->metadata ?? []), 'duplicate_payment_received_at' => now()->toIso8601String()],
                ]);

                return ['payment' => $payment, 'notice' => 'duplicate'];
            }

            $paidAt = now();
            $payment->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'confirmed_by' => $confirmedBy?->id,
            ]);

            $orderStatus = $order->status;

            if ($order->channel === 'online' && $order->transaction_type === 'product' && $orderStatus === 'pending') {
                $orderStatus = 'ready';
            }

            if ($order->channel === 'cashier' && in_array($order->transaction_type, ['service', 'mixed'], true) && $orderStatus === 'pending') {
                $orderStatus = 'completed';
            }

            $order->update([
                'payment_method' => $payment->method,
                'payment_status' => 'paid',
                'paid_at' => $paidAt,
                'cashier_id' => $confirmedBy?->id ?? $order->cashier_id,
                'status' => $orderStatus,
            ]);

            if ($order->booking_id) {
                $order->booking()
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'confirmed',
                        'hold_expires_at' => null,
                    ]);
            }

            $order->payments()
                ->whereKeyNot($payment->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            return ['payment' => $payment, 'notice' => 'paid'];
        }, 3);

        /** @var Payment $freshPayment */
        $freshPayment = $result['payment']->fresh('order');

        if ($result['notice']) {
            if ($result['notice'] === 'late') {
                $this->notifier->send(
                    'payment_review',
                    'Pembayaran terlambat perlu diperiksa',
                    "Pembayaran transaksi #{$freshPayment->order_id} diterima setelah transaksi dibatalkan.",
                    route('admin.resources.edit', ['resource' => 'orders', 'record' => $freshPayment->order_id]),
                );
            } elseif ($result['notice'] === 'amount') {
                $this->notifier->send(
                    'payment_review',
                    'Nominal pembayaran perlu diperiksa',
                    "Nominal pembayaran transaksi #{$freshPayment->order_id} tidak cocok dengan total transaksi.",
                    route('admin.resources.edit', ['resource' => 'orders', 'record' => $freshPayment->order_id]),
                );
            } elseif ($result['notice'] === 'duplicate') {
                $this->notifier->send(
                    'payment_review',
                    'Pembayaran ganda perlu diperiksa',
                    "Ada pembayaran tambahan untuk transaksi #{$freshPayment->order_id} yang sudah lunas.",
                    route('admin.resources.edit', ['resource' => 'orders', 'record' => $freshPayment->order_id]),
                );
            } else {
                $this->notifier->send(
                    'payment_paid',
                    'Pembayaran diterima',
                    "Transaksi #{$freshPayment->order_id} sebesar Rp ".number_format($freshPayment->amount, 0, ',', '.').' sudah lunas.',
                    route('admin.resources.edit', ['resource' => 'orders', 'record' => $freshPayment->order_id]),
                );
            }
        }

        return $freshPayment;
    }

    public function confirmCash(Order $order, User $cashier): Payment
    {
        $order->refresh();

        if ($order->payment_method !== 'cash') {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pembayaran tunai yang dapat dikonfirmasi manual.',
            ]);
        }

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'payment' => 'Transaksi yang sudah dibatalkan tidak dapat dibayar.',
            ]);
        }

        $payment = $order->payments()->where('method', 'cash')->latest('id')->first();

        if (! $payment) {
            $payment = $this->createForOrder($order, 'cash');
        }

        if ($payment->status === 'pending' && $payment->expires_at?->isPast()) {
            $this->expire($payment);

            throw ValidationException::withMessages([
                'payment' => 'Batas pembayaran tunai sudah habis. Buat transaksi atau booking baru.',
            ]);
        }

        if (! in_array($payment->status, ['pending', 'paid'], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini tidak lagi aktif dan tidak dapat dikonfirmasi.',
            ]);
        }

        $payment = $this->markPaid($payment, $cashier);

        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran tidak dapat dikonfirmasi otomatis dan sudah dikirim ke pemeriksaan admin.',
            ]);
        }

        return $payment;
    }

    public function expireDuePayments(int $limit = 100): int
    {
        $payments = Payment::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($payments as $payment) {
            $this->expire($payment);
        }

        $processed = $payments->count();
        $remaining = max(0, $limit - $processed);

        if ($remaining > 0) {
            $orphanOrders = Order::query()
                ->where('payment_status', 'unpaid')
                ->where('status', '!=', 'cancelled')
                ->whereHas('booking', fn ($query) => $query->whereNotNull('hold_expires_at')->where('hold_expires_at', '<=', now()))
                ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'paid'))
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($orphanOrders as $order) {
                $this->cancelOrder($order, 'Batas pembayaran booking sudah habis.');
                $processed++;
            }
        }

        $remaining = max(0, $limit - $processed);
        if ($remaining > 0) {
            $orphanBookings = Booking::query()
                ->where('status', 'pending')
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '<=', now())
                ->whereDoesntHave('transaction')
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($orphanBookings as $booking) {
                DB::transaction(function () use ($booking): void {
                    Booking::query()
                        ->whereKey($booking->id)
                        ->where('status', 'pending')
                        ->where('hold_expires_at', '<=', now())
                        ->lockForUpdate()
                        ->update(['status' => 'cancelled']);
                }, 3);
                $processed++;
            }
        }

        return $processed;
    }

    public function expire(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            [$order, $payment] = $this->lockOrderAndPayment($payment);

            if ($payment->status !== 'pending') {
                return $payment;
            }

            if ($order->payment_status === 'paid' || $order->payments()->whereKeyNot($payment->id)->where('status', 'paid')->exists()) {
                $payment->update(['status' => 'cancelled']);

                return $payment->refresh();
            }

            $this->cancelLockedOrder($order, 'Pembayaran kedaluwarsa.');
            $payment->update(['status' => 'expired']);

            return $payment->refresh();
        }, 3);
    }

    public function cancelOrder(Order $order, string $reason = 'Transaksi dibatalkan oleh admin.'): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $order = $this->lockOrder($order);
            $this->cancelLockedOrder($order, $reason);

            return $order->refresh();
        }, 3);
    }

    public function fail(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            [$order, $payment] = $this->lockOrderAndPayment($payment);

            if ($payment->status === 'pending') {
                if ($order->payment_status === 'paid' || $order->payments()->whereKeyNot($payment->id)->where('status', 'paid')->exists()) {
                    $payment->update(['status' => 'cancelled']);

                    return $payment->refresh();
                }

                $this->cancelLockedOrder($order, $reason);
                $payment->update([
                    'status' => 'failed',
                    'metadata' => [...($payment->metadata ?? []), 'failure_reason' => $reason],
                ]);
            }

            return $payment->refresh();
        }, 3);
    }

    private function cancelLockedOrder(Order $order, string $reason): void
    {
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Transaksi yang sudah lunas tidak boleh dibatalkan sebelum pembayaran dikembalikan.',
            ]);
        }

        if (! $order->stock_released_at) {
            foreach ($order->items()->where('item_type', 'product')->get() as $item) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
                $product?->increment('stock', $item->quantity);
            }
        }

        $notes = $order->notes;
        if ($reason !== '' && ! str_contains((string) $notes, $reason)) {
            $notes = trim(implode("\n", array_filter([$notes, $reason])));
        }
        $order->update([
            'status' => 'cancelled',
            'stock_released_at' => $order->stock_released_at ?? now(),
            'notes' => $notes,
        ]);
        $order->payments()->where('status', 'pending')->update(['status' => 'cancelled']);

        if ($order->booking_id) {
            $order->booking()->update(['status' => 'cancelled']);
        }
    }

    private function lockOrder(Order|int $order): Order
    {
        $orderId = $order instanceof Order ? $order->getKey() : $order;
        $snapshot = Order::query()->select(['id', 'booking_id'])->whereKey($orderId)->firstOrFail();

        if ($snapshot->booking_id) {
            Booking::query()->whereKey($snapshot->booking_id)->lockForUpdate()->first();
        }

        return Order::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array{Order, Payment} */
    private function lockOrderAndPayment(Payment $payment): array
    {
        $order = $this->lockOrder($payment->order_id);
        $payment = Payment::query()
            ->whereKey($payment->getKey())
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$order, $payment];
    }
}
