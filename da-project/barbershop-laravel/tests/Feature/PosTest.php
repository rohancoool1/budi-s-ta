<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_screen_is_transactional_and_does_not_show_revenue_summary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('Pelanggan walk-in')
            ->assertSee('Terbitkan antrean')
            ->assertSee('name="service_time"', false)
            ->assertDontSee('QRIS')
            ->assertDontSee('Pendapatan hari ini')
            ->assertDontSee('Pendapatan bulan ini')
            ->assertDontSee('name="payment_status"', false);
    }

    public function test_cash_walk_in_is_unpaid_until_cashier_confirms_it(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(9, 0));
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::where('slug', 'signature')->firstOrFail();
        $barber = Barber::where('slug', 'made')->firstOrFail();
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;

        $response = $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Pelanggan Walk-in',
            'phone' => '081234567890',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'service_time' => '10:00',
            'products' => [$product->id => 2],
            'discount' => 10000,
            'payment_method' => 'cash',
            'notes' => 'Transaksi uji POS.',
        ]);

        $order = Order::latest('id')->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $response->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]));
        $this->assertSame('cashier', $order->channel);
        $this->assertSame('mixed', $order->transaction_type);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $order->status);
        $this->assertSame(now()->toDateString(), $order->queue_date->toDateString());
        $this->assertSame('A001', $order->queue_code);
        $this->assertSame('10:00', $order->service_starts_at->format('H:i'));
        $this->assertSame('10:50', $order->service_ends_at->format('H:i'));
        $this->assertSame($service->price + ($product->price * 2) - 10000, $order->total);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => $startingStock - 2]);
        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Antrean A001')
            ->assertSee('Konfirmasi uang diterima');

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);

        $this->actingAs($admin)->post(route('admin.orders.confirm-cash', $order))->assertRedirect();
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->travelBack();
    }

    public function test_product_only_pos_transaction_does_not_receive_a_service_queue_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Pembeli Produk Kasir',
            'products' => [$product->id => 1],
            'discount' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::where('customer_name', 'Pembeli Produk Kasir')->firstOrFail();

        $this->assertSame('product', $order->transaction_type);
        $this->assertSame('completed', $order->status);
        $this->assertNull($order->queue_date);
        $this->assertNull($order->queue_number);
        $this->assertNull($order->queue_code);
    }

    public function test_pos_and_booking_share_one_queue_and_one_barber_schedule(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 0));
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::where('slug', 'signature')->firstOrFail();
        $barber = Barber::where('slug', 'made')->firstOrFail();
        $date = now()->toDateString();

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => $barber->slug,
            'service_id' => $service->slug,
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'name' => 'Booking Sebelum Walk-in',
            'phone' => '081211110001',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Walk-in Bentrok Booking',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'service_time' => '10:30',
            'discount' => 0,
            'payment_method' => 'cash',
        ])->assertSessionHasErrorsIn('pos', 'appointment_time');

        $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Walk-in Setelah Booking',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'service_time' => '10:50',
            'discount' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => $barber->slug,
            'service_id' => $service->slug,
            'appointment_date' => $date,
            'appointment_time' => '11:00',
            'name' => 'Booking Bentrok Walk-in',
            'phone' => '081211110002',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('appointment_time');

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => $barber->slug,
            'service_id' => $service->slug,
            'appointment_date' => $date,
            'appointment_time' => '11:40',
            'name' => 'Booking Setelah Walk-in',
            'phone' => '081211110003',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $bookingOrder = Booking::where('name', 'Booking Sebelum Walk-in')->firstOrFail()->transaction;
        $walkInOrder = Order::where('customer_name', 'Walk-in Setelah Booking')->firstOrFail();
        $lastBookingOrder = Booking::where('name', 'Booking Setelah Walk-in')->firstOrFail()->transaction;

        $this->assertSame('A001', $bookingOrder->queue_code);
        $this->assertSame('A002', $walkInOrder->queue_code);
        $this->assertSame('A003', $lastBookingOrder->queue_code);
        $this->assertSame('10:50', $walkInOrder->service_starts_at->format('H:i'));
        $this->assertSame('11:40', $walkInOrder->service_ends_at->format('H:i'));
        $this->travelBack();
    }

    public function test_qris_walk_in_is_rejected_without_creating_a_transaction(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::where('slug', 'fade')->firstOrFail();
        $barber = Barber::where('slug', 'rio')->firstOrFail();
        $ordersBefore = Order::count();

        $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'QRIS Walk-in',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'service_time' => '10:00',
            'payment_method' => 'qris',
        ])->assertSessionHasErrorsIn('pos', 'payment_method');

        $this->assertSame($ordersBefore, Order::count());
    }

    public function test_pos_pending_panel_only_shows_transactions_created_by_pos(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->post(route('orders.store'), [
            'name' => 'Pembeli Produk Aplikasi',
            'phone' => '081211112222',
            'email' => 'produk@example.com',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ])->assertRedirect();
        $onlineOrder = Order::where('customer_name', 'Pembeli Produk Aplikasi')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('Hanya transaksi yang dibuat langsung melalui Kasir POS.')
            ->assertDontSee('data-pending-payment-card="'.$onlineOrder->id.'"', false);

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'orders']))
            ->assertOk()
            ->assertSee('Pembeli Produk Aplikasi')
            ->assertSee('Konfirmasi bayar');
    }

    public function test_completing_booking_updates_the_existing_transaction_without_duplicate(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $date = now()->addDay()->toDateString();

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => $date,
            'appointment_time' => '14:00',
            'name' => 'Booking Masuk Riwayat',
            'phone' => '081244445555',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $booking = Booking::where('name', 'Booking Masuk Riwayat')->firstOrFail();
        $order = Order::where('booking_id', $booking->id)->firstOrFail();
        $payload = [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => $date,
            'appointment_time' => '14:00',
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => 'completed',
            'notes' => null,
        ];

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $payload)
            ->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Order::where('booking_id', $booking->id)->count());
    }
}
