<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\AdminNotifier;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AdminNotifier $notifier,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('order', [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'payment_method' => ['required', 'in:cash'],
            'cart_json' => ['required', 'json'],
        ]);

        $requestedItems = collect(json_decode($data['cart_json'], true))
            ->filter(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) > 0)
            ->groupBy(fn ($item) => (int) $item['id'])
            ->map(fn ($group, $productId) => [
                'id' => (int) $productId,
                'quantity' => max(1, min(10, $group->sum(fn ($item) => (int) ($item['quantity'] ?? 1)))),
            ])
            ->values();

        if ($requestedItems->isEmpty()) {
            throw ValidationException::withMessages(['cart_json' => 'Keranjang Anda masih kosong.'])->errorBag('order');
        }

        $order = DB::transaction(function () use ($data, $requestedItems): Order {
            $items = $requestedItems->map(function (array $item): array {
                $product = Product::whereKey($item['id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages(['cart_json' => 'Salah satu produk sudah tidak tersedia.'])->errorBag('order');
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'cart_json' => "Stok {$product->name} hanya tersisa {$product->stock}.",
                    ])->errorBag('order');
                }

                $product->decrement('stock', $item['quantity']);

                return [
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'service_id' => null,
                    'barber_id' => null,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'line_total' => $product->price * $item['quantity'],
                ];
            });

            $order = Order::create([
                'customer_name' => $data['name'],
                'phone' => $data['phone'],
                'email' => ($data['email'] ?? null) ?: null,
                'address' => null,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'channel' => 'online',
                'transaction_type' => 'product',
                'subtotal' => $items->sum('line_total'),
                'discount' => 0,
                'total' => $items->sum('line_total'),
                'status' => 'pending',
            ]);

            $order->items()->createMany($items->all());

            return $order;
        });

        $expiryMinutes = config('payments.product_cash_expiry_minutes');

        try {
            $payment = $this->payments->createForOrder(
                $order,
                'cash',
                now()->addMinutes($expiryMinutes),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->payments->cancelOrder($order, 'Pembuatan pembayaran pesanan gagal.');

            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran belum dapat dibuat. Stok pesanan sudah dilepas kembali.',
            ])->errorBag('order');
        }

        $this->notifier->send(
            'order_created',
            'Pesanan produk baru',
            "Pesanan #{$order->id} dari {$order->customer_name} menunggu pembayaran tunai.",
            route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]),
        );

        return to_route('payments.show', $payment)
            ->with('success', 'Pesanan disimpan. Tunjukkan kode transaksi dan bayar tunai di kasir.');
    }
}
