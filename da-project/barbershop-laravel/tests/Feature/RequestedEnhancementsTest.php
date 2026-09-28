<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestedEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_duration_is_configured_globally_from_admin_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'services']))
            ->assertOk()
            ->assertDontSee('Durasi')
            ->assertDontSee('Menit');

        $durationSetting = SiteSetting::where('key', 'service_duration_minutes')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'settings', 'record' => $durationSetting]))
            ->assertOk()
            ->assertSee('Durasi layanan global (menit)')
            ->assertSee('Masukkan 5–240 menit');

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'settings', 'record' => $durationSetting]), [
                'key' => 'service_duration_minutes',
                'value' => '60',
                'group' => 'operations',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'services']), [
            'name' => 'Layanan Durasi Tetap',
            'slug' => 'layanan-durasi-tetap',
            'price' => 35000,
            'description' => 'Layanan untuk memverifikasi estimasi tetap.',
            'sort_order' => 99,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, Service::where('slug', 'layanan-durasi-tetap')->firstOrFail()->duration_minutes);

        $this->get(route('booking'))
            ->assertOk()
            ->assertSee('data-service-duration="60"', false)
            ->assertSee('max="21:00"', false)
            ->assertSee('Estimasi layanan 60 menit')
            ->assertSee('tepat saat layanan sebelumnya selesai');
    }

    public function test_pos_shows_javascript_tabs_and_defaults_to_current_editable_time(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(13, 27));
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('data-pos-tab="service"', false)
            ->assertSee('data-pos-tab="product"', false)
            ->assertSee('data-pos-panel="service"', false)
            ->assertSee('data-pos-panel="product"', false)
            ->assertSee('name="service_time"', false)
            ->assertSee('value="13:27"', false)
            ->assertSee('data-use-current-time="true"', false)
            ->assertSee('estimasi layanan 45 menit');

        $this->travelBack();
    }

    public function test_configured_duration_synchronizes_booking_and_pos_without_a_gap(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 0));
        SiteSetting::where('key', 'service_duration_minutes')->update(['value' => '60']);

        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::where('slug', 'signature')->firstOrFail();
        $capster = Barber::where('slug', 'made')->firstOrFail();

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => $capster->slug,
            'service_id' => $service->slug,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'name' => 'Pelanggan Booking Durasi Global',
            'phone' => '081234567890',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Pelanggan POS Tanpa Jeda',
            'service_id' => $service->id,
            'barber_id' => $capster->id,
            'service_time' => '10:00',
            'discount' => 0,
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::where('name', 'Pelanggan Booking Durasi Global')->firstOrFail();
        $walkIn = Order::where('customer_name', 'Pelanggan POS Tanpa Jeda')->firstOrFail();

        $this->assertSame(60, $booking->duration_minutes);
        $this->assertSame('09:00', $booking->starts_at->format('H:i'));
        $this->assertSame('10:00', $booking->ends_at->format('H:i'));
        $this->assertSame('10:00', $walkIn->service_starts_at->format('H:i'));
        $this->assertSame('11:00', $walkIn->service_ends_at->format('H:i'));

        $this->travelBack();
    }

    public function test_store_operating_hours_are_configurable_and_used_everywhere(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $opening = SiteSetting::where('key', 'store_open_time')->firstOrFail();
        $closing = SiteSetting::where('key', 'store_close_time')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', ['resource' => 'settings', 'record' => $opening]))
            ->assertOk()
            ->assertSee('Jam buka toko')
            ->assertSee('type="time"', false);

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'settings', 'record' => $opening]), [
                'key' => $opening->key,
                'value' => '08:00',
                'group' => 'operations',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'settings', 'record' => $closing]), [
                'key' => $closing->key,
                'value' => '20:00',
                'group' => 'operations',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('booking'))
            ->assertOk()
            ->assertSee('data-open-time="08:00"', false)
            ->assertSee('data-close-time="20:00"', false)
            ->assertSee('min="08:00"', false)
            ->assertSee('max="19:15"', false);

        $this->actingAs($admin)->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('data-open-time="08:00"', false)
            ->assertSee('data-close-time="20:00"', false)
            ->assertSee('min="08:00"', false)
            ->assertSee('max="19:15"', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('08:00—20:00');

        $service = Service::where('slug', 'signature')->firstOrFail();
        $capster = Barber::where('slug', 'made')->firstOrFail();

        $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => $capster->slug,
            'service_id' => $service->slug,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '07:59',
            'name' => 'Pelanggan Sebelum Buka',
            'phone' => '081234567890',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors([
            'appointment_time' => 'Booking hanya tersedia pukul 08:00–19:15.',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.resources.update', ['resource' => 'settings', 'record' => $closing]), [
                'key' => $closing->key,
                'value' => '07:30',
                'group' => 'operations',
            ])
            ->assertSessionHasErrors('value');

        $this->assertSame('20:00', $closing->fresh()->value);
    }

    public function test_product_stock_is_visible_and_embedded_in_cart_controls(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $product->update(['stock' => 3]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('Stok tersedia: 3')
            ->assertSee('"stock":3', false);
    }

    public function test_customer_can_download_pdf_invoice_with_web_and_whatsapp_links(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        SiteSetting::where('key', 'phone')->update(['value' => '0812 1111 2222']);

        $this->post(route('orders.store'), [
            'name' => 'Pelanggan Invoice',
            'phone' => '081234567890',
            'email' => 'invoice@example.test',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ])->assertRedirect();

        $order = Order::where('customer_name', 'Pelanggan Invoice')->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();

        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Unduh invoice PDF')
            ->assertSee(route('payments.invoice', $payment), false)
            ->assertSee('https://wa.me/6281211112222', false)
            ->assertDontSee('Status@if', false);

        $pdf = $this->get(route('payments.invoice', $payment));

        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="invoice-homcuts-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT).'.pdf"');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_unified_customer_statuses_match_booking_and_product_workflows(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->post(route('orders.store'), [
            'name' => 'Pelanggan Status Produk',
            'phone' => '081234567891',
            'email' => null,
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ])->assertRedirect();

        $order = Order::where('customer_name', 'Pelanggan Status Produk')->firstOrFail();
        $this->assertSame('Belum bayar', $order->workflow_label);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertRedirect();

        $this->assertSame('Sudah dibayar / menunggu', $order->fresh()->workflow_label);
        $order->update(['status' => 'completed']);
        $this->assertSame('Sudah diambil', $order->fresh()->workflow_label);
    }
}
