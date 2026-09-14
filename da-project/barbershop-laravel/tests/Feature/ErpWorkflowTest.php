<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AdminActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_immediately_creates_transaction_payment_and_admin_notification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::where('slug', 'signature')->firstOrFail();
        $barber = Barber::where('slug', 'made')->firstOrFail();

        $response = $this->post(route('bookings.store'), $this->bookingPayload([
            'name' => 'Booking ERP',
        ]));

        $booking = Booking::where('name', 'Booking ERP')->firstOrFail();
        $order = Order::where('booking_id', $booking->id)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame($barber->id, $booking->barber_id);
        $this->assertSame($service->id, $booking->service_catalog_id);
        $this->assertSame(50, $booking->duration_minutes);
        $this->assertSame('10:50', $booking->ends_at->format('H:i'));
        $this->assertNotNull($booking->hold_expires_at);

        $this->assertSame('booking', $order->channel);
        $this->assertSame('service', $order->transaction_type);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame($booking->appointment_date->toDateString(), $order->queue_date->toDateString());
        $this->assertSame('A001', $order->queue_code);
        $this->assertSame($booking->starts_at->toDateTimeString(), $order->service_starts_at->toDateTimeString());
        $this->assertSame($booking->ends_at->toDateTimeString(), $order->service_ends_at->toDateTimeString());
        $this->assertSame($service->price, $order->total);
        $this->assertSame('cash', $payment->method);
        $this->assertSame('cash', $payment->provider);
        $this->assertSame('pending', $payment->status);
        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Bayar tunai di kasir.')
            ->assertSee('Nomor antrean')
            ->assertSee('A001')
            ->assertSee('data-status-url', false)
            ->assertDontSee('QRIS');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_type' => 'service',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'quantity' => 1,
        ]);

        $notification = $admin->notifications()->get()
            ->first(fn ($item) => ($item->data['kind'] ?? null) === 'booking_created');

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Booking ERP', $notification->data['message']);

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'bookings', 'record' => $booking]))
            ->assertOk()
            ->assertSee('Harga layanan')
            ->assertSee('Rp '.number_format($service->price, 0, ',', '.'))
            ->assertSee('Konfirmasi uang diterima');
        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]))
            ->assertOk()
            ->assertSee('Konfirmasi uang diterima');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('booking.status', 'confirmed');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        $this->getJson(route('payments.status', $payment))
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('booking_status', 'confirmed');
        $this->actingAs($admin)
            ->getJson(route('admin.notifications.index', [
                'booking_ids' => $booking->id,
                'order_ids' => $order->id,
            ]))
            ->assertOk()
            ->assertJsonPath('bookings.0.status', 'confirmed')
            ->assertJsonPath('bookings.0.payment_status', 'paid')
            ->assertJsonPath('orders.0.payment_status', 'paid');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->hold_expires_at);
    }

    public function test_booking_queue_numbers_are_globally_unique_across_visit_dates(): void
    {
        $firstDate = now()->addDays(20)->toDateString();
        $secondDate = now()->addDays(21)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $firstDate,
            'appointment_time' => '08:00',
            'name' => 'Antrean Pertama',
        ]))->assertRedirect();
        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $firstDate,
            'appointment_time' => '10:00',
            'name' => 'Antrean Kedua',
        ]))->assertRedirect();
        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $secondDate,
            'appointment_time' => '08:00',
            'name' => 'Antrean Hari Baru',
        ]))->assertRedirect();

        $this->assertSame('A001', Booking::where('name', 'Antrean Pertama')->firstOrFail()->transaction->queue_code);
        $this->assertSame('A002', Booking::where('name', 'Antrean Kedua')->firstOrFail()->transaction->queue_code);
        $this->assertSame('A003', Booking::where('name', 'Antrean Hari Baru')->firstOrFail()->transaction->queue_code);
    }

    public function test_admin_reschedule_is_reflected_on_the_customer_status_page_without_changing_the_unique_queue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $originalDate = now()->addDays(22)->toDateString();
        $newDate = now()->addDays(23)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $newDate,
            'appointment_time' => '08:00',
            'name' => 'Antrean Tanggal Tujuan',
        ]))->assertRedirect();
        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $originalDate,
            'appointment_time' => '10:00',
            'name' => 'Booking Dipindahkan',
        ]))->assertRedirect();

        $booking = Booking::where('name', 'Booking Dipindahkan')->firstOrFail();
        $order = $booking->transaction()->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();
        $this->assertSame('A002', $order->queue_code);

        $this->actingAs($admin)->put(route('admin.resources.update', [
            'resource' => 'bookings',
            'record' => $booking,
        ]), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => $newDate,
            'appointment_time' => '12:00',
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => 'pending',
            'notes' => 'Jadwal dipindahkan oleh admin.',
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $order->refresh();

        $this->assertSame($newDate, $booking->appointment_date->toDateString());
        $this->assertSame('12:00', $booking->starts_at->format('H:i'));
        $this->assertNotNull($booking->schedule_changed_at);
        $this->assertSame($newDate, $order->queue_date->toDateString());
        $this->assertSame('A002', $order->queue_code);
        $this->assertSame($booking->starts_at->toDateTimeString(), $order->service_starts_at->toDateTimeString());
        $this->assertSame($booking->ends_at->toDateTimeString(), $order->service_ends_at->toDateTimeString());

        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Jadwal booking diperbarui oleh admin.')
            ->assertSee('A002')
            ->assertSee('data-booking-status', false);

        $this->getJson(route('payments.status', $payment))
            ->assertOk()
            ->assertJsonPath('booking.schedule', $booking->starts_at->translatedFormat('d M Y, H:i'))
            ->assertJsonPath('booking.end_time', $booking->ends_at->format('H:i'))
            ->assertJsonPath('booking.barber', $booking->barber->name)
            ->assertJsonPath('booking.queue_code', 'A002')
            ->assertJsonPath('booking.schedule_changed_at', $booking->schedule_changed_at->toIso8601String());
    }

    public function test_booking_duration_overlap_is_blocked_but_an_adjacent_slot_is_allowed(): void
    {
        $date = now()->addDays(7)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'name' => 'Booking Awal',
        ]))->assertRedirect();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '10:30',
            'name' => 'Booking Bentrok',
        ]))->assertSessionHasErrors('appointment_time');

        $this->assertDatabaseMissing('bookings', ['name' => 'Booking Bentrok']);

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '10:50',
            'name' => 'Booking Berdampingan',
        ]))->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'name' => 'Booking Berdampingan',
            'artist_id' => 'made',
        ]);
        $this->assertSame(2, Booking::whereIn('name', ['Booking Awal', 'Booking Berdampingan'])->count());
    }

    public function test_quick_booking_assigns_the_first_free_barber(): void
    {
        $date = now()->addDays(8)->toDateString();
        $made = Barber::where('slug', 'made')->firstOrFail();
        $rio = Barber::where('slug', 'rio')->firstOrFail();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '13:00',
            'name' => 'Pelanggan Made',
        ]))->assertRedirect();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'booking_type' => 'service',
            'artist_id' => null,
            'appointment_date' => $date,
            'appointment_time' => '13:10',
            'name' => 'Booking Cepat',
        ]))->assertRedirect();

        $first = Booking::where('name', 'Pelanggan Made')->firstOrFail();
        $quick = Booking::where('name', 'Booking Cepat')->firstOrFail();

        $this->assertSame($made->id, $first->barber_id);
        $this->assertSame($rio->id, $quick->barber_id);
        $this->assertSame($rio->slug, $quick->artist_id);
    }

    public function test_admin_cannot_create_a_booking_that_conflicts_with_an_existing_slot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $date = now()->addDays(9)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '15:00',
            'name' => 'Booking Pelanggan',
        ]))->assertRedirect();

        $ordersBefore = Order::count();

        $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'bookings']), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => $date,
            'appointment_time' => '15:30',
            'name' => 'Booking Admin Bentrok',
            'phone' => '081299990001',
            'status' => 'pending',
            'notes' => null,
        ])->assertSessionHasErrors('appointment_time');

        $this->assertDatabaseMissing('bookings', ['name' => 'Booking Admin Bentrok']);
        $this->assertSame($ordersBefore, Order::count());
    }

    public function test_cash_confirmation_marks_a_product_order_paid_and_ready_for_pickup(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pembeli Tunai',
        ]))->assertRedirect();

        $order = Order::where('customer_name', 'Pembeli Tunai')->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'orders']))
            ->assertOk()
            ->assertSee('Pembeli Tunai')
            ->assertSee('Konfirmasi bayar');

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]))
            ->assertOk()
            ->assertSee('Menunggu pembayaran tunai')
            ->assertSee('Konfirmasi uang diterima');

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('ready', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->stock_released_at);
        $this->assertSame($startingStock - 2, $product->fresh()->stock);
    }

    public function test_an_expired_product_payment_restores_stock_only_once(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pesanan Kedaluwarsa',
        ]))->assertRedirect();

        $order = Order::where('customer_name', 'Pesanan Kedaluwarsa')->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $this->assertSame($startingStock - 2, $product->fresh()->stock);
        $this->travelTo($payment->expires_at->copy()->addSecond());

        $this->artisan('payments:expire')->assertSuccessful();

        $order->refresh();
        $releasedAt = $order->stock_released_at;

        $this->assertNotNull($releasedAt);
        $this->assertSame($startingStock, $product->fresh()->stock);

        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertSame($startingStock, $product->fresh()->stock);
        $this->assertTrue($releasedAt->equalTo($order->fresh()->stock_released_at));
        $this->travelBack();
    }

    public function test_cash_confirmation_is_idempotent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pembeli Tunai',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ]))->assertRedirect();

        $cashOrder = Order::where('customer_name', 'Pembeli Tunai')->firstOrFail();
        $cashPayment = Payment::where('order_id', $cashOrder->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $cashOrder))
            ->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $cashOrder]));

        $firstPaidAt = $cashPayment->fresh()->paid_at;

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $cashOrder))
            ->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $cashOrder]));

        $cashOrder->refresh();
        $cashPayment->refresh();

        $this->assertSame('paid', $cashOrder->payment_status);
        $this->assertSame('ready', $cashOrder->status);
        $this->assertSame('paid', $cashPayment->status);
        $this->assertSame($admin->id, $cashPayment->confirmed_by);
        $this->assertTrue($firstPaidAt->equalTo($cashPayment->paid_at));
        $this->assertSame(1, $cashOrder->payments()->count());
        $this->assertSame(1, $admin->notifications()->get()
            ->filter(fn ($item) => ($item->data['kind'] ?? null) === 'payment_paid')
            ->count());

    }

    public function test_expired_booking_releases_the_slot_but_a_paid_booking_keeps_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $date = now()->addDays(10)->toDateString();
        $payload = $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '16:00',
            'name' => 'Booking Akan Kedaluwarsa',
        ]);

        $this->post(route('bookings.store'), $payload)->assertRedirect();
        $expiredBooking = Booking::where('name', 'Booking Akan Kedaluwarsa')->firstOrFail();
        $expiredOrder = $expiredBooking->transaction()->firstOrFail();
        $expiredPayment = $expiredOrder->latestPayment()->firstOrFail();

        $this->travelTo($expiredPayment->expires_at->copy()->addSecond());
        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertSame('cancelled', $expiredBooking->fresh()->status);
        $this->assertSame('cancelled', $expiredOrder->fresh()->status);
        $this->assertSame('expired', $expiredPayment->fresh()->status);

        $this->post(route('bookings.store'), [...$payload, 'name' => 'Pengganti Slot'])->assertRedirect();
        $replacement = Booking::where('name', 'Pengganti Slot')->firstOrFail();
        $replacementPayment = $replacement->transaction->latestPayment;
        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $replacement->transaction))
            ->assertRedirect();

        $this->travelTo($replacementPayment->expires_at->copy()->addSecond());
        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertSame('paid', $replacementPayment->fresh()->status);
        $this->assertSame('confirmed', $replacement->fresh()->status);
        $this->post(route('bookings.store'), [...$payload, 'name' => 'Bentrok Booking Lunas'])
            ->assertSessionHasErrors('appointment_time');
        $this->travelBack();
    }

    public function test_expired_cash_payment_cannot_be_confirmed_and_restores_product_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Tunai Terlambat',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ]))->assertRedirect();

        $order = Order::where('customer_name', 'Tunai Terlambat')->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();
        $this->travelTo($payment->expires_at->copy()->addSecond());

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertSessionHasErrors('payment');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame($startingStock, $product->fresh()->stock);
        $this->travelBack();
    }

    public function test_status_only_booking_update_preserves_the_financial_and_duration_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('bookings.store'), $this->bookingPayload([
            'name' => 'Snapshot Booking',
            'payment_method' => 'cash',
        ]))->assertRedirect();

        $booking = Booking::where('name', 'Snapshot Booking')->firstOrFail();
        $order = $booking->transaction()->firstOrFail();
        $item = $order->items()->where('item_type', 'service')->firstOrFail();
        $originalEnd = $booking->ends_at->toDateTimeString();
        $originalTotal = $order->total;
        $originalItemPrice = $item->unit_price;

        Service::where('slug', 'signature')->update(['duration_minutes' => 120, 'price' => 999000]);

        $this->actingAs($admin)->put(route('admin.resources.update', [
            'resource' => 'bookings',
            'record' => $booking,
        ]), [
            'booking_type' => $booking->booking_type,
            'artist_id' => $booking->artist_id,
            'service_id' => $booking->service_id,
            'appointment_date' => $booking->appointment_date->format('Y-m-d'),
            'appointment_time' => substr($booking->appointment_time, 0, 5),
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => 'completed',
            'notes' => 'Selesai tanpa mengubah snapshot.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame($originalEnd, $booking->fresh()->ends_at->toDateTimeString());
        $this->assertSame($originalTotal, $order->fresh()->total);
        $this->assertSame($originalItemPrice, $item->fresh()->unit_price);
    }

    public function test_paid_booking_cannot_be_cancelled_without_a_refund(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('bookings.store'), $this->bookingPayload(['name' => 'Booking Lunas']))->assertRedirect();

        $booking = Booking::where('name', 'Booking Lunas')->firstOrFail();
        $order = $booking->transaction()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertRedirect();

        $this->actingAs($admin)->put(route('admin.resources.update', [
            'resource' => 'bookings',
            'record' => $booking,
        ]), [
            'booking_type' => $booking->booking_type,
            'artist_id' => $booking->artist_id,
            'service_id' => $booking->service_id,
            'appointment_date' => $booking->appointment_date->format('Y-m-d'),
            'appointment_time' => substr($booking->appointment_time, 0, 5),
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => 'cancelled',
            'notes' => null,
        ])->assertSessionHasErrors('status');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_public_checkout_rejects_qris_without_creating_records(): void
    {
        $date = now()->addDays(12)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '11:00',
            'name' => 'Booking QRIS Ditolak',
            'payment_method' => 'qris',
        ]))->assertSessionHasErrors('payment_method');

        $this->assertDatabaseMissing('bookings', ['name' => 'Booking QRIS Ditolak']);

        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Produk QRIS Ditolak',
            'payment_method' => 'qris',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ]))->assertSessionHasErrorsIn('order', 'payment_method');

        $this->assertDatabaseMissing('orders', ['customer_name' => 'Produk QRIS Ditolak']);
        $this->assertSame($startingStock, $product->fresh()->stock);
    }

    public function test_customer_cannot_book_the_current_minute(): void
    {
        $this->travelTo(now()->startOfMinute()->addSeconds(20));

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => now()->toDateString(),
            'appointment_time' => now()->format('H:i'),
            'name' => 'Booking Pada Menit Sekarang',
        ]))->assertSessionHasErrors('appointment_time');

        $this->assertDatabaseMissing('bookings', ['name' => 'Booking Pada Menit Sekarang']);
        $this->travelBack();
    }

    public function test_booking_respects_shop_hours_service_duration_and_barber_shift(): void
    {
        $date = now()->addDays(14)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '06:59',
            'name' => 'Terlalu Pagi',
        ]))->assertSessionHasErrors('appointment_time');

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '21:11',
            'name' => 'Melewati Tutup',
        ]))->assertSessionHasErrors('appointment_time');

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '21:10',
            'name' => 'Selesai Tepat Tutup',
        ]))->assertRedirect();

        Barber::where('slug', 'rio')->update(['work_start_time' => '10:00', 'work_end_time' => '20:00']);
        $this->post(route('bookings.store'), $this->bookingPayload([
            'artist_id' => 'rio',
            'appointment_date' => now()->addDays(15)->toDateString(),
            'appointment_time' => '19:30',
            'name' => 'Melewati Shift Barber',
        ]))->assertSessionHasErrors('appointment_time');

        $this->post(route('bookings.store'), $this->bookingPayload([
            'artist_id' => 'rio',
            'appointment_date' => now()->addDays(15)->toDateString(),
            'appointment_time' => '19:10',
            'name' => 'Dalam Shift Barber',
        ]))->assertRedirect();

        $this->assertDatabaseMissing('bookings', ['name' => 'Terlalu Pagi']);
        $this->assertDatabaseMissing('bookings', ['name' => 'Melewati Tutup']);
        $this->assertDatabaseMissing('bookings', ['name' => 'Melewati Shift Barber']);
        $this->assertDatabaseHas('bookings', ['name' => 'Selesai Tepat Tutup']);
        $this->assertDatabaseHas('bookings', ['name' => 'Dalam Shift Barber']);
    }

    public function test_payment_amount_mismatch_is_sent_to_review_and_does_not_mark_order_paid(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $startingStock = $product->stock;
        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Nominal Berubah',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ]))->assertRedirect();

        $order = Order::where('customer_name', 'Nominal Berubah')->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();
        $order->update(['total' => $order->total + 1]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertSessionHasErrors('payment');

        $this->assertSame('review', $payment->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($startingStock, $product->fresh()->stock);
    }

    public function test_notification_endpoints_are_scoped_to_the_authenticated_admin(): void
    {
        $adminA = User::factory()->create(['is_admin' => true]);
        $adminB = User::factory()->create(['is_admin' => true]);

        $adminA->notify(new AdminActivityNotification(
            'test_a_1',
            'Milik Admin A Pertama',
            'Notifikasi pertama untuk admin A.',
            route('admin.dashboard'),
        ));
        $adminA->notify(new AdminActivityNotification(
            'test_a_2',
            'Milik Admin A Kedua',
            'Notifikasi kedua untuk admin A.',
            route('admin.dashboard'),
        ));
        $adminB->notify(new AdminActivityNotification(
            'test_b',
            'Milik Admin B',
            'Notifikasi khusus admin B.',
            route('admin.dashboard'),
        ));

        $notificationA = $adminA->notifications()->where('data->kind', 'test_a_1')->firstOrFail();
        $notificationB = $adminB->notifications()->where('data->kind', 'test_b')->firstOrFail();

        $this->actingAs($adminA)
            ->getJson(route('admin.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonFragment(['title' => 'Milik Admin A Pertama'])
            ->assertJsonMissing(['title' => 'Milik Admin B']);

        $this->actingAs($adminA)
            ->postJson(route('admin.notifications.read', $notificationB->id))
            ->assertNotFound();

        $this->assertNull($notificationB->fresh()->read_at);

        $this->actingAs($adminA)
            ->postJson(route('admin.notifications.read', $notificationA->id))
            ->assertOk()
            ->assertJson(['read' => true]);

        $this->assertNotNull($notificationA->fresh()->read_at);

        $this->actingAs($adminA)
            ->postJson(route('admin.notifications.read-all'))
            ->assertOk()
            ->assertJson(['read' => true]);

        $this->assertSame(0, $adminA->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $adminB->fresh()->unreadNotifications()->count());
        $this->assertNull($notificationB->fresh()->read_at);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_replace([
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => now()->addDays(7)->toDateString(),
            'appointment_time' => '10:00',
            'name' => 'Pelanggan Booking',
            'phone' => '081234567890',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function productOrderPayload(Product $product, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Pelanggan Produk',
            'phone' => '081211112222',
            'email' => 'pelanggan@example.com',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 2]]),
        ], $overrides);
    }
}
