<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GalleryEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarbershopTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_pages_are_separate_and_reachable(): void
    {
        $pages = [
            route('home') => 'Potongan tajam.',
            route('booking') => 'Kursi Anda',
            route('shop') => 'Rambut rapi,',
            route('gallery') => 'Potongan yang',
            route('about') => 'Barbershop dengan',
            route('contact') => 'Mari bicara',
        ];

        foreach ($pages as $url => $heading) {
            $this->get($url)->assertOk()->assertSee($heading);
        }
    }

    public function test_homcuts_brand_and_contact_profile_are_shown(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('HOMCUTS')
            ->assertSee('homcuts-logo-transparent.png')
            ->assertSee('homcuts-storefront.png')
            ->assertDontSee('BRASS &amp; BLADE', false);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Jl. Ir. Sutami')
            ->assertSee('Bulurokeng, Makassar')
            ->assertSee('0882-0207-03600')
            ->assertSee('@homcuts_')
            ->assertSee('@homcuts');

        $this->assertFileExists(public_path('homcuts-logo.jpg'));
        $this->assertFileExists(public_path('homcuts-logo-transparent.png'));
        $this->assertFileExists(public_path('homcuts-storefront.png'));
    }

    public function test_homcuts_gallery_is_separate_from_unique_booking_services(): void
    {
        $gallery = GalleryEntry::query()->orderBy('sort_order')->get();
        $services = Service::query()->where('is_active', true)->orderBy('sort_order')->get();

        $this->assertCount(25, $gallery);
        $this->assertSame('gallery-images/homcuts-look-01.jpg', $gallery->first()->image_path);
        $this->assertSame('gallery-images/homcuts-look-25.jpg', $gallery->last()->image_path);
        $this->assertSame(2, $gallery->where('style', 'Textured Fringe Fade')->count());
        $this->assertSame(3, $gallery->where('style', 'Low Fade Side Part')->count());
        $this->assertSame($services->count(), $services->pluck('name')->unique()->count());

        foreach ($gallery as $entry) {
            $this->assertFileExists(public_path($entry->image_path));
        }

        $galleryPage = $this->get(route('gallery'))->assertOk();
        $galleryPage
            ->assertSee('REFERENSI GAYA')
            ->assertSee('Low Fade Textured Crop')
            ->assertSee('Scissor Cut Process')
            ->assertDontSee('CERITA PELANGGAN');
        $this->assertSame(25, substr_count($galleryPage->getContent(), 'class="gallery-photo'));

        $bookingPage = $this->get(route('booking'))->assertOk();
        foreach ($services as $service) {
            $this->assertSame(1, substr_count($bookingPage->getContent(), 'value="'.$service->slug.'"'));
        }

        $admin = User::factory()->create(['is_admin' => true]);
        $posPage = $this->actingAs($admin)->get(route('admin.pos.create'))->assertOk();
        foreach ($services as $service) {
            $this->assertSame(1, substr_count($posPage->getContent(), '<option value="'.$service->id.'" data-price="'));
        }
    }

    public function test_confirmation_is_displayed_as_a_modal(): void
    {
        $response = $this->withSession(['booking_success' => 'Booking tersimpan.'])->get(route('booking'));

        $response->assertOk();
        $response->assertSee('id="success-modal"', false);
        $response->assertSee('Jadwal Anda tercatat.');
    }

    public function test_booking_can_be_saved(): void
    {
        $response = $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '09:30',
            'name' => 'Test Client',
            'phone' => '+62 812 0000 0000',
            'payment_method' => 'cash',
        ]);

        $booking = Booking::where('name', 'Test Client')->firstOrFail();
        $order = Order::where('booking_id', $booking->id)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $response->assertRedirect(route('payments.show', $payment));
        $this->assertDatabaseHas('bookings', [
            'name' => 'Test Client',
            'artist_id' => 'made',
            'service_id' => 'signature',
        ]);
        $this->assertSame('made', $booking->artist_id);
        $this->assertNotNull($booking->starts_at);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $payment->status);
    }

    public function test_booking_form_uses_shop_hours_instead_of_a_full_day(): void
    {
        $this->get(route('booking'))
            ->assertOk()
            ->assertSee('min="07:00"', false)
            ->assertSee('max="21:30"', false)
            ->assertSee('selesai sebelum 22.00');
    }

    public function test_order_total_is_calculated_on_the_server(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $response = $this->post(route('orders.store'), [
            'name' => 'Test Client',
            'phone' => '+62 812 0000 0000',
            'email' => 'client@example.com',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 2]]),
        ]);

        $order = Order::where('customer_name', 'Test Client')->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Test Client',
            'total' => 60000,
            'channel' => 'online',
            'payment_status' => 'unpaid',
            'address' => null,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'line_total' => 60000,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);
    }

    public function test_contact_message_can_be_saved(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '+62 812 0000 0000',
            'subject' => 'general',
            'message' => 'I would like to ask about opening hours.',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('contact_success');
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'client@example.com',
            'subject' => 'general',
            'status' => 'new',
        ]);
    }
}
