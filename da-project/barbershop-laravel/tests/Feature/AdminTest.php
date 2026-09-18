<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\GalleryEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_access_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_pending_work_and_unified_finance_sections(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Booking menunggu pembayaran')
            ->assertSee('Booking aktif / lunas')
            ->assertSee('Pesanan produk menunggu')
            ->assertSee('Tunai perlu dikonfirmasi')
            ->assertSee('Produk siap diambil')
            ->assertSee('Pendapatan hari ini')
            ->assertSee('Pendapatan bulan ini')
            ->assertSee('Transaksi terbaru')
            ->assertSee('data-admin-sort', false)
            ->assertSee('data-sort-region="dashboard-content"', false)
            ->assertSee('admin-data-table', false)
            ->assertDontSee('name="transaction_sort"', false)
            ->assertDontSee('Klik judul kolom untuk mengurutkan');
    }

    public function test_dashboard_recent_transactions_exclude_cancelled_orders_and_bookings_regardless_of_payment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $barber = Barber::query()->firstOrFail();
        $service = Service::query()->firstOrFail();
        $startsAt = now()->addDay()->setTime(10, 0);

        $cancelledBooking = Booking::query()->create([
            'booking_type' => 'artist',
            'artist_id' => $barber->slug,
            'barber_id' => $barber->id,
            'service_id' => $service->slug,
            'service_catalog_id' => $service->id,
            'appointment_date' => $startsAt->toDateString(),
            'appointment_time' => $startsAt->format('H:i'),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
            'duration_minutes' => $service->duration_minutes,
            'name' => 'Booking Batal Lunas',
            'phone' => '081200000001',
            'status' => 'cancelled',
        ]);

        Order::query()->create([
            'customer_name' => 'Booking Batal Lunas',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'channel' => 'booking',
            'transaction_type' => 'service',
            'booking_id' => $cancelledBooking->id,
            'subtotal' => 30000,
            'discount' => 0,
            'total' => 30000,
            'status' => 'completed',
        ]);

        Order::query()->create([
            'customer_name' => 'Transaksi Batal Belum Bayar',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'channel' => 'cashier',
            'transaction_type' => 'service',
            'subtotal' => 30000,
            'discount' => 0,
            'total' => 30000,
            'status' => 'cancelled',
        ]);

        Order::query()->create([
            'customer_name' => 'Transaksi Aktif',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'channel' => 'cashier',
            'transaction_type' => 'service',
            'subtotal' => 30000,
            'discount' => 0,
            'total' => 30000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $recentCustomers = $response->viewData('recentOrders')->pluck('customer_name')->all();

        $this->assertSame(['Transaksi Aktif'], $recentCustomers);
        $response
            ->assertSee('Transaksi Aktif')
            ->assertDontSee('Booking Batal Lunas')
            ->assertDontSee('Transaksi Batal Belum Bayar');
    }

    public function test_admin_operational_pages_include_live_refresh_regions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-live-region="dashboard-content"', false)
            ->assertSee('refreshPageRegions', false);

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'bookings']))
            ->assertOk()
            ->assertSee('data-live-region="resource-table"', false);

        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('data-live-region="pos-pending-payments"', false);
    }

    public function test_primary_admin_navigation_orders_products_below_services_and_highlights_transactions(): void
    {
        $navigation = AdminResources::navigation();
        $keys = array_column($navigation, 'key');
        $highlighted = collect($navigation)->where('highlighted', true)->pluck('key')->all();

        $this->assertSame(['bookings', 'orders'], array_slice($keys, 0, 2));
        $this->assertSame(array_search('services', $keys, true) + 1, array_search('products', $keys, true));
        $this->assertSame(['bookings', 'orders'], $highlighted);
    }

    public function test_admin_uses_capster_label_and_friendly_resource_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $navigation = collect(AdminResources::navigation());
        $capsterNavigation = $navigation->firstWhere('key', 'barbers');

        $this->assertSame('capsters', $capsterNavigation['route_key']);
        $this->assertSame('Capster', $capsterNavigation['label']);

        $this->actingAs($admin)
            ->get(route('admin.resources.index', ['resource' => 'capsters']))
            ->assertOk()
            ->assertSee('Pengelolaan data')
            ->assertSee('Capster')
            ->assertSee('/admin/capsters/create', false)
            ->assertDontSee('/admin/barbers', false)
            ->assertDontSee('Kelola foto profil, spesialisasi, biodata, dan ketersediaan barber.');

        $this->actingAs($admin)
            ->get(route('admin.pos.create'))
            ->assertOk()
            ->assertSee('Capster yang melayani')
            ->assertSee('Pilih capster')
            ->assertDontSee('Barber yang melayani')
            ->assertDontSee('Pilih barber');
    }

    public function test_every_admin_resource_table_has_clickable_ajax_sorting_headers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (AdminResources::keys() as $resource) {
            $this->actingAs($admin)
                ->get(route('admin.resources.index', ['resource' => $resource]))
                ->assertOk()
                ->assertSee('data-admin-sort', false)
                ->assertSee('data-sort-region="resource-table"', false)
                ->assertSee('admin-data-table', false)
                ->assertDontSee('name="sort"', false)
                ->assertDontSee('name="direction"', false)
                ->assertDontSee('Klik judul kolom untuk mengurutkan');
        }

        Product::query()->update(['is_active' => false]);
        $visible = Product::query()->firstOrFail();
        $visible->update(['is_active' => true, 'name' => 'Produk Paling Awal']);

        $this->actingAs($admin)
            ->get(route('admin.resources.index', [
                'resource' => 'products',
                'active' => '1',
                'sort' => 'name',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('Produk Paling Awal');
    }

    public function test_every_visible_admin_column_can_be_sorted_in_both_directions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (AdminResources::keys() as $resource) {
            $definition = AdminResources::get($resource);

            foreach ($definition['columns'] as $column) {
                $sort = $column['sort'] ?? $column['key'];

                foreach (['asc', 'desc'] as $direction) {
                    $this->actingAs($admin)
                        ->get(route('admin.resources.index', compact('resource', 'sort', 'direction')))
                        ->assertOk();
                }
            }
        }

        foreach (['created_at', 'channel', 'customer_name', 'barber_name', 'transaction_type', 'status', 'payment_status', 'total'] as $sort) {
            $this->actingAs($admin)
                ->get(route('admin.dashboard', ['transaction_sort' => $sort, 'transaction_direction' => 'asc']))
                ->assertOk();
        }
    }

    public function test_admin_can_sign_in_and_sign_out(): void
    {
        $admin = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->post(route('admin.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_can_create_update_and_delete_a_product(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'products']), [
            'name' => 'Test Pomade',
            'slug' => 'test-pomade',
            'category' => 'Styling',
            'price' => 120000,
            'stock' => 8,
            'size' => '80 G',
            'badge' => 'BARU',
            'description' => 'Dibuat dari dasbor admin.',
            'sort_order' => 20,
            'is_active' => '1',
            'image_upload' => $this->fakeImage('produk.png'),
        ]);

        $response->assertRedirect(route('admin.resources.index', ['resource' => 'products']));
        $product = Product::where('slug', 'test-pomade')->firstOrFail();
        Storage::disk('public')->assertExists(str($product->image_path)->after('storage/')->toString());

        $this->actingAs($admin)->put(route('admin.resources.update', ['resource' => 'products', 'record' => $product]), [
            'name' => 'Updated Pomade',
            'slug' => 'test-pomade',
            'category' => 'Styling',
            'price' => 125000,
            'stock' => 5,
            'size' => '80 G',
            'badge' => null,
            'description' => 'Diperbarui dari dasbor admin.',
            'sort_order' => 20,
            'is_active' => '1',
        ])->assertRedirect(route('admin.resources.index', ['resource' => 'products']));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Pomade',
            'price' => 125000,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.resources.destroy', ['resource' => 'products', 'record' => $product]))
            ->assertRedirect(route('admin.resources.index', ['resource' => 'products']));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing(str($product->image_path)->after('storage/')->toString());
    }

    public function test_unknown_admin_resource_is_not_exposed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertNotFound();
    }

    public function test_admin_cannot_create_a_duplicate_service_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'services']), [
            'name' => 'Skin fade',
            'slug' => 'skin-fade-duplikat',
            'duration_minutes' => 60,
            'price' => 210000,
            'description' => 'Nama layanan ini sudah digunakan.',
            'sort_order' => 20,
            'is_active' => '1',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Service::where('name', 'Skin fade')->count());
    }

    public function test_admin_can_upload_barber_and_gallery_photos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'barbers']), [
            'name' => 'Barber Uji',
            'slug' => 'barber-uji',
            'initials' => 'BU',
            'role' => 'Spesialis potongan uji',
            'bio' => 'Biografi barber untuk pengujian.',
            'sort_order' => 30,
            'is_active' => '1',
            'image_upload' => $this->fakeImage('barber.png'),
        ])->assertRedirect(route('admin.resources.index', ['resource' => 'barbers']));

        $barber = Barber::where('slug', 'barber-uji')->firstOrFail();
        Storage::disk('public')->assertExists(str($barber->image_path)->after('storage/')->toString());

        $this->actingAs($admin)->post(route('admin.resources.store', ['resource' => 'gallery']), [
            'style' => 'Model Uji',
            'client' => 'Pelanggan Uji',
            'barber_id' => $barber->id,
            'position' => '50% 50%',
            'quote' => 'Hasil potongannya sangat rapi.',
            'sort_order' => 30,
            'is_published' => '1',
            'image_upload' => $this->fakeImage('galeri.png'),
        ])->assertRedirect(route('admin.resources.index', ['resource' => 'gallery']));

        $gallery = GalleryEntry::where('style', 'Model Uji')->firstOrFail();
        Storage::disk('public')->assertExists(str($gallery->image_path)->after('storage/')->toString());
    }

    public function test_photo_resources_show_upload_fields_in_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['products', 'barbers', 'gallery'] as $resource) {
            $this->actingAs($admin)
                ->get(route('admin.resources.create', ['resource' => $resource]))
                ->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="image_upload"', false)
                ->assertSee('type="file"', false);
        }
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
    }
}
