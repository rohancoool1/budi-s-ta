<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Barber;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlackBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_halaman_utama_menampilkan_ringkasan_informasi(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('HOMCUTS')
            ->assertSee('Booking')
            ->assertSee('Lihat produk');
    }

    public function test_02_booking_sebelum_toko_buka_ditolak(): void
    {
        $response = $this->from(route('booking'))->post(route('bookings.store'), $this->bookingPayload([
            'appointment_time' => '06:59',
            'name' => 'Uji Sebelum Buka',
        ]));

        $response
            ->assertRedirect(route('booking'))
            ->assertSessionHasErrors('appointment_time')
            ->assertSessionHasErrors([
                'appointment_time' => 'Booking hanya tersedia pukul 07:00–21:30.',
            ]);
        $this->assertDatabaseMissing('bookings', ['name' => 'Uji Sebelum Buka']);
    }

    public function test_03_booking_dengan_jadwal_bertabrakan_ditolak(): void
    {
        $date = now()->addDays(3)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'name' => 'Pelanggan Pertama',
        ]))->assertRedirect();

        $response = $this->from(route('booking'))->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $date,
            'appointment_time' => '10:30',
            'name' => 'Pelanggan Bertabrakan',
        ]));

        $response
            ->assertRedirect(route('booking'))
            ->assertSessionHasErrors('appointment_time');
        $this->assertStringContainsString(
            'Slot ini sudah digunakan booking atau walk-in',
            session('errors')->first('appointment_time'),
        );
        $this->assertDatabaseMissing('bookings', ['name' => 'Pelanggan Bertabrakan']);
    }

    public function test_04_booking_yang_selesai_melewati_jam_tutup_ditolak(): void
    {
        $response = $this->from(route('booking'))->post(route('bookings.store'), $this->bookingPayload([
            'service_id' => 'complete',
            'appointment_time' => '21:00',
            'name' => 'Uji Melewati Tutup',
        ]));

        $response
            ->assertRedirect(route('booking'))
            ->assertSessionHasErrors('appointment_time');
        $this->assertStringContainsString(
            'agar selesai sebelum toko tutup',
            session('errors')->first('appointment_time'),
        );
        $this->assertDatabaseMissing('bookings', ['name' => 'Uji Melewati Tutup']);
    }

    public function test_05_booking_pada_jadwal_tersedia_berhasil_dan_menunggu_pembayaran(): void
    {
        $response = $this->post(route('bookings.store'), $this->bookingPayload([
            'name' => 'Booking Berhasil',
        ]));

        $booking = Booking::where('name', 'Booking Berhasil')->firstOrFail();
        $order = $booking->transaction()->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();

        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame('pending', $booking->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $payment->status);
        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Bayar tunai di kasir.');
    }

    public function test_06_perubahan_jadwal_booking_admin_tampil_di_status_pelanggan(): void
    {
        $admin = $this->admin();
        $oldDate = now()->addDays(5)->toDateString();
        $newDate = now()->addDays(6)->toDateString();

        $this->post(route('bookings.store'), $this->bookingPayload([
            'appointment_date' => $oldDate,
            'appointment_time' => '09:00',
            'name' => 'Booking Diubah Admin',
        ]))->assertRedirect();

        $booking = Booking::where('name', 'Booking Diubah Admin')->firstOrFail();
        $payment = $booking->transaction->latestPayment;

        $this->actingAs($admin)->put(route('admin.resources.update', [
            'resource' => 'bookings',
            'record' => $booking,
        ]), [
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => $newDate,
            'appointment_time' => '13:00',
            'name' => $booking->name,
            'phone' => $booking->phone,
            'status' => 'pending',
            'notes' => 'Jadwal diubah untuk pengujian black-box.',
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame($newDate, $booking->appointment_date->toDateString());
        $this->assertSame('13:00', $booking->starts_at->format('H:i'));
        $this->assertNotNull($booking->schedule_changed_at);

        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Jadwal booking diperbarui oleh admin.')
            ->assertSee($booking->starts_at->translatedFormat('d M Y, H:i'));
    }

    public function test_07_pemesanan_produk_menampilkan_struk_pembayaran_tunai(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $response = $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pembeli Produk',
        ]));

        $order = Order::where('customer_name', 'Pembeli Produk')->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();

        $response->assertRedirect(route('payments.show', $payment));
        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Transaksi #'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Pesanan produk')
            ->assertSee('Total')
            ->assertSee('Rp '.number_format($order->total, 0, ',', '.'))
            ->assertSee('Bayar tunai di kasir.');
    }

    public function test_08_pemesanan_produk_melebihi_stok_ditolak(): void
    {
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();
        $product->update(['stock' => 1]);

        $response = $this->from(route('shop'))->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pembeli Melebihi Stok',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 2]]),
        ]));

        $response
            ->assertRedirect(route('shop'))
            ->assertSessionHasErrorsIn('order', 'cart_json');
        $this->assertStringContainsString(
            'hanya tersisa 1',
            session('errors')->getBag('order')->first('cart_json'),
        );
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Pembeli Melebihi Stok']);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_09_kasir_mengonfirmasi_uang_dan_pembayaran_menjadi_lunas(): void
    {
        $admin = $this->admin();
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->post(route('orders.store'), $this->productOrderPayload($product, [
            'name' => 'Pembayaran Dikonfirmasi',
        ]))->assertRedirect();

        $order = Order::where('customer_name', 'Pembayaran Dikonfirmasi')->firstOrFail();
        $payment = $order->latestPayment()->firstOrFail();

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('admin.orders.confirm-cash', $order))
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.status', 'ready')
            ->assertJsonPath('payment.status', 'paid');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_10_pengguna_belum_login_diarahkan_ke_login_admin(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_11_login_admin_dengan_kredensial_salah_ditolak(): void
    {
        $this->admin([
            'email' => 'admin.blackbox@homcuts.test',
            'password' => 'KataSandiBenar123!',
        ]);

        $this->from(route('login'))->post(route('admin.login.store'), [
            'email' => 'admin.blackbox@homcuts.test',
            'password' => 'kata-sandi-salah',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_12_login_admin_dengan_kredensial_benar_diterima(): void
    {
        $admin = $this->admin([
            'email' => 'admin.blackbox@homcuts.test',
            'password' => 'KataSandiBenar123!',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin.blackbox@homcuts.test',
            'password' => 'KataSandiBenar123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan');
    }

    public function test_13_pos_mencatat_transaksi_layanan_pelanggan_walk_in(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 0));
        $admin = $this->admin();
        $service = Service::where('slug', 'signature')->firstOrFail();
        $barber = Barber::where('slug', 'made')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.pos.store'), [
            'customer_name' => 'Pelanggan Walk-in Black-box',
            'phone' => '081234567800',
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'service_time' => '10:00',
            'discount' => 0,
            'payment_method' => 'cash',
            'notes' => 'Data uji black-box POS.',
        ]);

        $order = Order::where('customer_name', 'Pelanggan Walk-in Black-box')->firstOrFail();
        $response->assertRedirect(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]));
        $this->assertSame('cashier', $order->channel);
        $this->assertSame('service', $order->transaction_type);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotNull($order->queue_code);
        $this->assertSame('10:00', $order->service_starts_at->format('H:i'));
        $this->travelBack();
    }

    public function test_14_admin_menambah_data_utama_dan_data_tampil_di_toko(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'products']), [
            ...$this->productAdminPayload(),
            'image_upload' => $this->fakeImage('produk-black-box.png'),
        ]);

        $response->assertRedirect(route('admin.resources.index', ['resource' => 'products']));
        $product = Product::where('slug', 'produk-black-box')->firstOrFail();
        Storage::disk('public')->assertExists(str($product->image_path)->after('storage/')->toString());

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('Produk Black-box');
    }

    public function test_15_admin_mengubah_data_utama_dan_perubahan_tampil_di_toko(): void
    {
        $admin = $this->admin();
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.resources.update', [
            'resource' => 'products',
            'record' => $product,
        ]), [
            'name' => 'Natur Hair Tonic Edisi Black-box',
            'slug' => $product->slug,
            'category' => $product->category,
            'price' => 45000,
            'stock' => $product->stock,
            'size' => $product->size,
            'badge' => 'DIUJI',
            'description' => 'Deskripsi produk telah diperbarui melalui pengujian.',
            'sort_order' => $product->sort_order,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.resources.index', ['resource' => 'products']));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Natur Hair Tonic Edisi Black-box',
            'price' => 45000,
        ]);
        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('Natur Hair Tonic Edisi Black-box')
            ->assertSee('45.000');
    }

    public function test_16_admin_mendapat_konfirmasi_lalu_dapat_menghapus_data_utama(): void
    {
        $admin = $this->admin();
        $product = Product::where('slug', 'natur-hair-tonic-ginseng-90ml')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'products']))
            ->assertOk()
            ->assertSee("confirm('Hapus produk ini? Tindakan ini tidak dapat dibatalkan.')", false)
            ->assertSee('Hapus');

        $this->actingAs($admin)
            ->delete(route('admin.resources.destroy', ['resource' => 'products', 'record' => $product]))
            ->assertRedirect(route('admin.resources.index', ['resource' => 'products']));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->get(route('shop'))
            ->assertOk()
            ->assertDontSee($product->name);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_replace([
            'booking_type' => 'artist',
            'artist_id' => 'made',
            'service_id' => 'signature',
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => '09:30',
            'name' => 'Pelanggan Black-box',
            'phone' => '081234567890',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function productOrderPayload(Product $product, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Pembeli Black-box',
            'phone' => '081234567890',
            'email' => 'blackbox@example.test',
            'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]]),
        ], $overrides);
    }

    private function productAdminPayload(): array
    {
        return [
            'name' => 'Produk Black-box',
            'slug' => 'produk-black-box',
            'category' => 'Produk Uji',
            'price' => 50000,
            'stock' => 7,
            'size' => '50 ml',
            'badge' => 'UJI',
            'description' => 'Produk sementara untuk pengujian black-box.',
            'sort_order' => 99,
            'is_active' => '1',
        ];
    }

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_replace([
            'name' => 'Admin Black-box',
            'email' => 'admin-'.uniqid().'@homcuts.test',
            'password' => 'KataSandiBenar123!',
            'is_admin' => true,
        ], $overrides));
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
