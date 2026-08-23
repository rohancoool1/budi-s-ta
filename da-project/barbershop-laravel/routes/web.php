<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => view('home', [
    'artists' => config('barbershop.artists'),
    'services' => config('barbershop.services'),
]))->name('home');

Route::get('/booking', fn (Request $request) => view('booking', [
    'artists' => config('barbershop.artists'),
    'services' => config('barbershop.services'),
    'selectedArtist' => $request->string('artist')->toString(),
]))->name('booking');

Route::get('/shop', fn () => view('shop', [
    'products' => config('barbershop.products'),
]))->name('shop');

Route::get('/gallery', fn () => view('gallery', [
    'gallery' => config('barbershop.gallery'),
]))->name('gallery');

Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');

Route::post('/bookings', function (Request $request) {
    $data = $request->validate([
        'booking_type' => ['required', 'in:service,artist'],
        'artist_id' => ['nullable', 'required_if:booking_type,artist', 'in:made,rio,dani'],
        'service_id' => ['required', 'in:signature,fade,beard,complete'],
        'appointment_date' => ['required', 'date', 'after_or_equal:today'],
        'appointment_time' => ['required', 'date_format:H:i'],
        'name' => ['required', 'string', 'max:100'],
        'phone' => ['required', 'string', 'max:30'],
    ]);

    Storage::disk('local')->append('bookings.jsonl', json_encode([
        ...$data,
        'artist_id' => $data['artist_id'] ?? null,
        'created_at' => now()->toIso8601String(),
    ], JSON_UNESCAPED_SLASHES));

    return to_route('booking')->with('booking_success', 'Your appointment request has been saved. We will confirm it by WhatsApp shortly.');
})->name('bookings.store');

Route::post('/orders', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'phone' => ['required', 'string', 'max:30'],
        'email' => ['required', 'email', 'max:150'],
        'address' => ['required', 'string', 'max:500'],
        'payment_method' => ['required', 'in:qris,transfer,cod'],
        'cart_json' => ['required', 'json'],
    ]);

    $requestedItems = collect(json_decode($data['cart_json'], true));
    abort_if($requestedItems->isEmpty(), 422, 'Your bag is empty.');

    $catalog = collect(config('barbershop.products'))->keyBy('id');
    $items = $requestedItems->map(function ($item) use ($catalog) {
        $product = $catalog->get((int) ($item['id'] ?? 0));
        abort_if(! $product, 422, 'One of the selected products is unavailable.');
        $quantity = max(1, min(10, (int) ($item['quantity'] ?? 1)));

        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
        ];
    })->values();

    $total = $items->sum(fn ($item) => $item['price'] * $item['quantity']);

    Storage::disk('local')->append('orders.jsonl', json_encode([
        'customer_name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'],
        'address' => $data['address'],
        'payment_method' => $data['payment_method'],
        'items' => $items->all(),
        'total' => $total,
        'created_at' => now()->toIso8601String(),
    ], JSON_UNESCAPED_SLASHES));

    return to_route('shop')->with('order_success', 'Order confirmed. We will send payment and delivery details shortly.');
})->name('orders.store');

Route::post('/contact', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:150'],
        'phone' => ['nullable', 'string', 'max:30'],
        'subject' => ['required', 'in:general,booking,product,collaboration'],
        'message' => ['required', 'string', 'max:2000'],
    ]);

    Storage::disk('local')->append('messages.jsonl', json_encode([
        ...$data,
        'created_at' => now()->toIso8601String(),
    ], JSON_UNESCAPED_SLASHES));

    return to_route('contact')->with('contact_success', 'Thanks for reaching out. Our team will reply within one business day.');
})->name('contact.store');
