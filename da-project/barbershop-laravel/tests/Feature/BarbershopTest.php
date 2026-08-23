<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarbershopTest extends TestCase
{
    public function test_all_public_pages_are_separate_and_reachable(): void
    {
        $pages = [
            route('home') => 'Sharp cuts.',
            route('booking') => 'Your chair',
            route('shop') => 'Good hair,',
            route('gallery') => 'The look.',
            route('about') => 'A quieter kind',
            route('contact') => 'Let’s talk',
        ];

        foreach ($pages as $url => $heading) {
            $this->get($url)->assertOk()->assertSee($heading);
        }
    }

    public function test_confirmation_is_displayed_as_a_modal(): void
    {
        $response = $this->withSession(['booking_success' => 'Booking saved.'])->get(route('booking'));

        $response->assertOk();
        $response->assertSee('id="success-modal"', false);
        $response->assertSee('You’re on the books.');
    }

    public function test_booking_can_be_saved(): void
    {
        Storage::fake('local');

        $response = $this->post(route('bookings.store'), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '09:30',
            'name' => 'Test Client',
            'phone' => '+62 812 0000 0000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('booking_success');
        Storage::disk('local')->assertExists('bookings.jsonl');
    }

    public function test_order_total_is_calculated_on_the_server(): void
    {
        Storage::fake('local');

        $response = $this->post(route('orders.store'), [
            'name' => 'Test Client',
            'phone' => '+62 812 0000 0000',
            'email' => 'client@example.com',
            'address' => 'Renon, Denpasar',
            'payment_method' => 'qris',
            'cart_json' => json_encode([['id' => 1, 'quantity' => 2]]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('order_success');
        Storage::disk('local')->assertExists('orders.jsonl');
        $this->assertStringContainsString('370000', Storage::disk('local')->get('orders.jsonl'));
    }

    public function test_contact_message_can_be_saved(): void
    {
        Storage::fake('local');

        $response = $this->post(route('contact.store'), [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '+62 812 0000 0000',
            'subject' => 'general',
            'message' => 'I would like to ask about opening hours.',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('contact_success');
        Storage::disk('local')->assertExists('messages.jsonl');
    }
}
