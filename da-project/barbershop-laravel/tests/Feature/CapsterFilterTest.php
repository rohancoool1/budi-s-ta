<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapsterFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_capster_filter_only_returns_selected_capster_on_both_resource_aliases(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $selected = Barber::firstOrFail();
        foreach (['capsters', 'barbers'] as $resource) {
            $this->actingAs($admin)->get(route('admin.resources.index', [
                'resource' => $resource, 'capster' => $selected->id,
            ]))->assertOk()->assertSee('Semua capster')->assertSee('Reset filter')
                ->assertViewHas('records', fn ($records) => $records->count() === 1 && $records->first()->id === $selected->id);
        }
    }

    public function test_filter_combines_with_status_sorting_and_reset(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $selected = Barber::firstOrFail();
        $selected->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('admin.resources.index', [
            'resource' => 'capsters', 'capster' => $selected->id, 'active' => '1',
        ]))->assertOk()->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->get(route('admin.resources.index', [
            'resource' => 'capsters', 'capster' => $selected->id, 'active' => '0', 'sort' => 'name', 'direction' => 'desc',
        ]))->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1)
            ->assertSee('capster='.$selected->id, false);
        $this->get(route('admin.resources.index', ['resource' => 'capsters']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === Barber::count());
    }

    public function test_invalid_filter_does_not_change_query_or_expose_other_resources(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.resources.index', [
            'resource' => 'capsters', 'capster' => '1 OR 1=1',
        ]))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === Barber::count());
    }
}
