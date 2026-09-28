<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_admin_setting_controls_booking_and_product_payment_deadlines(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'payment_expiry_minutes'],
            ['value' => '75', 'group' => 'transaction'],
        );

        $this->post(route('bookings.store'), $this->bookingPayload(['name' => 'Batas Booking']))->assertRedirect();
        $bookingPayment = Booking::where('name', 'Batas Booking')->firstOrFail()->transaction->latestPayment;

        $product = Product::where('is_active', true)->where('stock', '>', 0)->firstOrFail();
        $this->post(route('orders.store'), [
            'name' => 'Batas Produk',
            'phone' => '081234567890',
            'email' => null,
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ])->assertRedirect();
        $productPayment = Order::where('customer_name', 'Batas Produk')->firstOrFail()->latestPayment;

        $this->assertEqualsWithDelta(75, $bookingPayment->created_at->diffInMinutes($bookingPayment->expires_at), 0.1);
        $this->assertEqualsWithDelta(75, $productPayment->created_at->diffInMinutes($productPayment->expires_at), 0.1);
    }

    public function test_unpaid_bookings_may_overlap_but_first_deposit_locks_the_slot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $date = now()->addDays(4)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'name' => 'Calon Pertama',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
        ]))->assertRedirect();
        $this->post(route('bookings.store'), $this->bookingPayload([
            'name' => 'Calon Kedua',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
        ]))->assertRedirect();

        $first = Booking::where('name', 'Calon Pertama')->firstOrFail();
        $second = Booking::where('name', 'Calon Kedua')->firstOrFail();
        $order = $first->transaction;

        $this->actingAs($admin)->post(route('admin.orders.confirm-deposit', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('partial', $order->payment_status);
        $this->assertSame('deposit', $first->fresh()->status);
        $this->assertSame((int) ceil($order->total / 2), $order->paid_amount);
        $this->assertSame($order->total - $order->paid_amount, $order->remaining_amount);
        $this->assertSame(1, $order->payments()->where('status', 'paid')->count());
        $this->assertSame(1, $order->payments()->where('status', 'pending')->count());

        $this->actingAs($admin)
            ->from(route('admin.resources.edit', ['resource' => 'bookings', 'record' => $second]))
            ->post(route('admin.orders.confirm-deposit', $second->transaction))
            ->assertSessionHasErrors('appointment_time');
        $this->actingAs($admin)
            ->from(route('admin.resources.edit', ['resource' => 'bookings', 'record' => $second]))
            ->post(route('admin.orders.confirm-cash', $second->transaction))
            ->assertSessionHasErrors('appointment_time');
        $this->assertSame('unpaid', $second->transaction->fresh()->payment_status);

        $this->actingAs($admin)->post(route('admin.orders.confirm-cash', $order))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame($order->total, $order->fresh()->paid_amount);
    }

    public function test_booking_must_be_saved_as_paid_before_it_can_be_completed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('bookings.store'), $this->bookingPayload(['name' => 'Tahap Booking']))->assertRedirect();
        $booking = Booking::where('name', 'Tahap Booking')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $this->adminBookingPayload($booking, 'completed'))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $this->adminBookingPayload($booking, 'deposit'))
            ->assertSessionHasNoErrors();
        $this->assertSame('partial', $booking->transaction->fresh()->payment_status);

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $this->adminBookingPayload($booking->fresh(), 'completed'))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $this->adminBookingPayload($booking->fresh(), 'confirmed'))
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $booking->transaction->fresh()->payment_status);

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'bookings', 'record' => $booking]), $this->adminBookingPayload($booking->fresh(), 'completed'))
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame('completed', $booking->transaction->fresh()->status);
    }

    public function test_product_status_selection_confirms_payment_before_collection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('is_active', true)->where('stock', '>', 0)->firstOrFail();
        $this->post(route('orders.store'), [
            'name' => 'Status Produk',
            'phone' => '081234567890',
            'email' => null,
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ])->assertRedirect();
        $order = Order::where('customer_name', 'Status Produk')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'orders', 'record' => $order]), ['status' => 'completed', 'notes' => null])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'orders', 'record' => $order]), ['status' => 'paid', 'notes' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'orders', 'record' => $order]), ['status' => 'completed', 'notes' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_quick_completion_requires_full_payment_and_synchronizes_booking(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('bookings.store'), $this->bookingPayload(['name' => 'Aksi Cepat Selesai']))->assertRedirect();
        $booking = Booking::where('name', 'Aksi Cepat Selesai')->firstOrFail();
        $order = $booking->transaction;

        $this->actingAs($admin)
            ->postJson(route('admin.orders.complete', $order))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)
            ->postJson(route('admin.orders.confirm-cash', $order))
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('booking.status', 'confirmed');

        $this->actingAs($admin)
            ->postJson(route('admin.orders.complete', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('booking.status', 'completed');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_transaction_table_exposes_deposit_payment_and_completion_quick_actions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('bookings.store'), $this->bookingPayload(['name' => 'Tombol Cepat DP']))->assertRedirect();
        $booking = Booking::where('name', 'Tombol Cepat DP')->firstOrFail();
        $order = $booking->transaction;

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'orders', 'tab' => 'booking']))
            ->assertOk()
            ->assertSee('data-confirm-deposit-for="'.$order->id.'"', false)
            ->assertSee('data-confirm-payment-for="'.$order->id.'"', false)
            ->assertDontSee('data-complete-for="'.$order->id.'"', false);

        $this->actingAs($admin)->post(route('admin.orders.confirm-cash', $order))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'orders', 'tab' => 'booking']))
            ->assertOk()
            ->assertDontSee('data-confirm-deposit-for="'.$order->id.'"', false)
            ->assertDontSee('data-confirm-payment-for="'.$order->id.'"', false)
            ->assertSee('data-complete-for="'.$order->id.'"', false);

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]))
            ->assertOk()
            ->assertSee('data-order-status-select="'.$order->id.'"', false)
            ->assertSee('value="paid"', false)
            ->assertSee('selected', false);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => now()->addDays(5)->toDateString(),
            'appointment_time' => '12:00',
            'name' => 'Pelanggan DP',
            'phone' => '081234567890',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function adminBookingPayload(Booking $booking, string $status): array
    {
        return [
            'booking_type' => $booking->booking_type,
            'artist_id' => $booking->artist_id,
            'service_id' => $booking->service_id,
            'appointment_date' => $booking->appointment_date->format('Y-m-d'),
            'appointment_time' => substr((string) $booking->appointment_time, 0, 5),
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => $status,
            'notes' => $booking->notes,
        ];
    }
}
