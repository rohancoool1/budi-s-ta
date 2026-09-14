<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\BookingAvailabilityService;
use App\Services\PaymentService;
use App\Services\QueueNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly QueueNumberService $queueNumbers,
        private readonly BookingAvailabilityService $availability,
    ) {}

    public function create(): View
    {
        return view('admin.pos.create', [
            'barbers' => Barber::where('is_active', true)->orderBy('sort_order')->get(),
            'services' => Service::where('is_active', true)->orderBy('sort_order')->get(),
            'products' => Product::where('is_active', true)->orderBy('sort_order')->get(),
            'recentSales' => Order::with(['items', 'booking'])
                ->where('channel', 'cashier')
                ->where('payment_method', 'cash')
                ->where('payment_status', 'unpaid')
                ->where('status', '!=', 'cancelled')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('pos', [
            'customer_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('is_active', true)],
            'barber_id' => ['nullable', 'required_with:service_id', Rule::exists('barbers', 'id')->where('is_active', true)],
            'service_time' => ['nullable', 'required_with:service_id', 'date_format:H:i'],
            'products' => ['nullable', 'array'],
            'products.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', 'in:cash'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $productQuantities = collect($data['products'] ?? [])
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0);

        if (empty($data['service_id']) && $productQuantities->isEmpty()) {
            throw ValidationException::withMessages([
                'service_id' => 'Pilih minimal satu layanan atau produk.',
            ])->errorBag('pos');
        }

        $order = DB::transaction(function () use ($data, $productQuantities, $request): Order {
            $items = collect();
            $slot = null;
            if (! empty($data['service_id'])) {
                $service = Service::whereKey($data['service_id'])->where('is_active', true)->firstOrFail();
                $barber = Barber::whereKey($data['barber_id'])->where('is_active', true)->firstOrFail();
                $this->ensureServiceTimeNotPast($data['service_time']);
                try {
                    $slot = $this->availability->resolve(
                        $service->slug,
                        now()->toDateString(),
                        $data['service_time'],
                        $barber->slug,
                        lock: true,
                    );
                } catch (ValidationException $exception) {
                    $exception->errorBag('pos');

                    throw $exception;
                }
                $service = $slot['service'];
                $barber = $slot['barber'];

                $items->push([
                    'item_type' => 'service',
                    'product_id' => null,
                    'service_id' => $service->id,
                    'barber_id' => $barber->id,
                    'product_name' => $service->name,
                    'unit_price' => $service->price,
                    'quantity' => 1,
                    'line_total' => $service->price,
                ]);
            }

            foreach ($productQuantities as $productId => $quantity) {
                $product = Product::whereKey($productId)->where('is_active', true)->lockForUpdate()->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        'products' => 'Salah satu produk sudah tidak tersedia.',
                    ])->errorBag('pos');
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'products' => "Stok {$product->name} hanya tersisa {$product->stock}.",
                    ])->errorBag('pos');
                }

                $product->decrement('stock', $quantity);
                $items->push([
                    'item_type' => 'product',
                    'product_id' => $product->id,
                    'service_id' => null,
                    'barber_id' => null,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => $product->price * $quantity,
                ]);
            }

            $subtotal = $items->sum('line_total');
            $discount = (int) ($data['discount'] ?? 0);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'Diskon tidak boleh lebih besar dari subtotal.',
                ])->errorBag('pos');
            }

            $hasService = $items->contains('item_type', 'service');
            $hasProduct = $items->contains('item_type', 'product');
            $order = Order::create([
                'customer_name' => ($data['customer_name'] ?? null) ?: 'Pelanggan Walk-in',
                'phone' => ($data['phone'] ?? null) ?: null,
                'email' => null,
                'address' => null,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'paid_at' => null,
                'channel' => 'cashier',
                'transaction_type' => $hasService && $hasProduct ? 'mixed' : ($hasService ? 'service' : 'product'),
                'service_starts_at' => $slot['starts_at'] ?? null,
                'service_ends_at' => $slot['ends_at'] ?? null,
                'booking_id' => null,
                'cashier_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'status' => $hasService ? 'pending' : 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($items->all());

            if ($hasService) {
                $order = $this->queueNumbers->assign($order, $slot['starts_at']);
            }

            return $order;
        });

        try {
            $this->payments->createForOrder($order, 'cash');
        } catch (Throwable $exception) {
            report($exception);
            $this->payments->cancelOrder($order, 'Pembuatan pembayaran transaksi kasir gagal.');

            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran belum dapat dibuat. Transaksi dibatalkan dan stok dikembalikan.',
            ])->errorBag('pos');
        }

        $queueMessage = $order->queue_code ? " Nomor antrean {$order->queue_code}." : '';

        return to_route('admin.resources.edit', ['resource' => 'orders', 'record' => $order])
            ->with('success', "Transaksi #{$order->id} tersimpan.{$queueMessage} Konfirmasi pembayaran setelah layanan selesai dan uang tunai diterima.");
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'barber_id' => ['required', Rule::exists('barbers', 'id')->where('is_active', true)],
            'service_time' => ['required', 'date_format:H:i'],
        ]);
        $this->ensureServiceTimeNotPast($data['service_time']);
        $service = Service::query()->findOrFail($data['service_id']);
        $barber = Barber::query()->findOrFail($data['barber_id']);
        $slot = $this->availability->resolve(
            $service->slug,
            now()->toDateString(),
            $data['service_time'],
            $barber->slug,
        );

        return response()->json([
            'available' => true,
            'message' => "Slot tersedia bersama {$slot['barber']->name}, {$slot['starts_at']->format('H:i')}–{$slot['ends_at']->format('H:i')}.",
        ]);
    }

    private function ensureServiceTimeNotPast(string $time): void
    {
        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            now()->toDateString().' '.$time,
            config('app.timezone'),
        );

        if ($startsAt->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages([
                'service_time' => 'Waktu layanan walk-in tidak boleh berada di masa lalu.',
            ])->errorBag('pos');
        }
    }
}
