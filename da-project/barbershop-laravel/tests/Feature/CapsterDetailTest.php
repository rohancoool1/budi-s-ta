<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapsterDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_details_are_restricted_to_admins(): void
    {
        $barber = Barber::firstOrFail();
        $this->get(route('admin.capsters.show', $barber))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.capsters.show', $barber))->assertForbidden();
    }

    public function test_summary_counts_only_this_capsters_completed_services(): void
    {
        $barber = Barber::firstOrFail();
        $other = Barber::where('id', '!=', $barber->id)->firstOrFail();
        $order = $this->serviceOrder($barber, 'completed', 2, 'Potong rambut uji');
        $order->items()->create(['item_type' => 'product', 'barber_id' => $barber->id,
            'product_name' => 'Produk tidak dihitung', 'unit_price' => 10000, 'quantity' => 5, 'line_total' => 50000]);
        $this->serviceOrder($barber, 'pending', 1, 'Layanan mendatang');
        $this->serviceOrder($barber, 'cancelled', 3, 'Layanan batal');
        $this->serviceOrder($other, 'completed', 9, 'Layanan capster lain');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.capsters.show', $barber))->assertOk()
            ->assertViewHas('summary', ['completed' => 2, 'pending' => 1, 'cancelled' => 3, 'transactions' => 1])
            ->assertViewHas('history', fn ($history) => $history->total() === 3)
            ->assertSee('Potong rambut uji')->assertSee('19/10/2026 10:00')
            ->assertDontSee('Produk tidak dihitung')->assertDontSee('Layanan capster lain')
            ->assertSee(route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]), false);
    }

    public function test_empty_history_and_missing_capster_are_handled(): void
    {
        $barber = Barber::firstOrFail();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.capsters.show', $barber))->assertOk()
            ->assertSee('Belum ada riwayat layanan untuk capster ini atau tidak ada hasil yang cocok dengan filter.')
            ->assertViewHas('summary', ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'transactions' => 0]);
        $this->get(route('admin.capsters.show', 999999999))->assertNotFound();
    }

    public function test_summary_covers_all_pages_and_list_has_detail_link_on_both_aliases(): void
    {
        $barber = Barber::firstOrFail();
        for ($i = 0; $i < 16; $i++) {
            $this->serviceOrder($barber, 'completed', 1, 'Layanan '.$i);
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.capsters.show', ['barber' => $barber, 'page' => 2, 'period' => 'month', 'month' => '2026-10']))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->count() === 1 && $history->total() === 16 && str_contains($history->url(1), 'month=2026-10'))
            ->assertViewHas('summary', fn ($summary) => $summary['completed'] === 16);
        foreach (['capsters', 'barbers'] as $resource) {
            $this->get(route('admin.resources.index', ['resource' => $resource]))->assertOk()
                ->assertSee(route('admin.capsters.show', $barber), false)->assertSee('Details');
        }
    }

    public function test_today_month_and_year_use_service_dates_and_exclusive_end_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Makassar']);
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 10, 19, 0, 5, 0, 'Asia/Makassar'));
        $barber = Barber::firstOrFail();
        foreach (['2026-10-18 23:59:59', '2026-10-19 00:00:00', '2026-10-19 23:59:59',
            '2026-10-20 00:00:00', '2026-11-01 00:00:00', '2027-01-01 00:00:00'] as $date) {
            $this->serviceOrder($barber, 'completed', 1, 'Layanan periode')->update(['service_starts_at' => $date]);
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach ([['period' => 'today', 'expected' => 2], ['period' => 'month', 'month' => '2026-10', 'expected' => 4],
            ['period' => 'year', 'year' => '2026', 'expected' => 5],
            ['period' => 'date', 'date' => '2026-11-01', 'expected' => 1]] as $case) {
            $expected = $case['expected'];
            unset($case['expected']);
            $this->get(route('admin.capsters.show', ['barber' => $barber, ...$case]))->assertOk()
                ->assertViewHas('history', fn ($history) => $history->total() === $expected)
                ->assertViewHas('summary', fn ($summary) => $summary['completed'] === $expected);
        }
        $this->travelBack();
    }

    public function test_sorting_changes_order_without_changing_period_summary(): void
    {
        $barber = Barber::firstOrFail();
        $first = $this->serviceOrder($barber, 'completed', 2, 'Alpha');
        $second = $this->serviceOrder($barber, 'completed', 10, 'Zulu');
        $this->serviceOrder($barber, 'completed', 99, 'Outside')->update(['service_starts_at' => '2025-01-01 10:00:00']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['asc' => $first, 'desc' => $second] as $direction => $expected) {
            foreach (['quantity', 'service', 'transaction'] as $sort) {
                $this->get(route('admin.capsters.show', [
                    'barber' => $barber, 'period' => 'month', 'month' => '2026-10',
                    'sort' => $sort, 'direction' => $direction,
                ]))->assertOk()->assertViewHas('history', fn ($history) =>
                    $history->total() === 2 && $history->first()->order_id === $expected->id)
                    ->assertViewHas('summary', fn ($summary) => $summary['completed'] === 12)
                    ->assertDontSee('Filter pelanggan')->assertDontSee('Terapkan filter kolom');
            }
        }
    }

    public function test_invalid_sort_falls_back_safely_and_old_column_filters_are_ignored(): void
    {
        $barber = Barber::firstOrFail();
        $this->serviceOrder($barber, 'completed', 1, 'Layanan aman');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.capsters.show', ['barber' => $barber,
                'sort' => 'orders.id; DROP TABLE orders', 'direction' => 'invalid',
                'customer' => 'Tidak cocok', 'status' => 'cancelled',
            ]))->assertOk()->assertViewHas('sort', 'date')->assertViewHas('direction', 'desc')
                ->assertViewHas('history', fn ($history) => $history->total() === 1);
    }

    public function test_invalid_period_inputs_are_rejected_and_missing_dates_use_created_date(): void
    {
        $barber = Barber::firstOrFail();
        $order = $this->serviceOrder($barber, 'completed', 1, 'Tanpa jadwal');
        $order->forceFill(['service_starts_at' => null, 'service_ends_at' => null,
            'created_at' => '2025-02-03 10:00:00'])->save();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson(route('admin.capsters.show', ['barber' => $barber, 'period' => 'month', 'month' => '2026-13']))
            ->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson(route('admin.capsters.show', ['barber' => $barber, 'period' => 'year']))
            ->assertUnprocessable()->assertJsonValidationErrors('year');
        $this->get(route('admin.capsters.show', ['barber' => $barber, 'period' => 'date', 'date' => '2025-02-03']))
            ->assertOk()->assertSee('Jadwal tidak tercatat')
            ->assertViewHas('history', fn ($history) => $history->total() === 1);
    }

    private function serviceOrder(Barber $barber, string $status, int $quantity, string $name): Order
    {
        $order = Order::create([
            'customer_name' => 'Pelanggan Uji', 'phone' => '081234567890',
            'payment_method' => 'cash', 'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'channel' => 'cashier', 'transaction_type' => 'service', 'status' => $status,
            'subtotal' => 50000 * $quantity, 'total' => 50000 * $quantity,
            'service_starts_at' => '2026-10-19 10:00:00', 'service_ends_at' => '2026-10-19 10:30:00',
        ]);
        $order->items()->create([
            'item_type' => 'service', 'barber_id' => $barber->id, 'product_name' => $name,
            'unit_price' => 50000, 'quantity' => $quantity, 'line_total' => 50000 * $quantity,
        ]);

        return $order;
    }
}
