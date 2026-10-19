<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapsterFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_capster_page_has_no_filters_and_ignores_old_filter_parameters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $selected = Barber::firstOrFail();
        $selected->update(['is_active' => false]);

        foreach (['capsters', 'barbers'] as $resource) {
            $this->actingAs($admin)->get(route('admin.resources.index', [
                'resource' => $resource, 'capster' => $selected->id, 'active' => '1',
            ]))->assertOk()
                ->assertDontSee('Filter capster')->assertDontSee('Terapkan filter')->assertDontSee('Reset filter')
                ->assertViewHas('resourceFilters', [])
                ->assertViewHas('records', fn ($records) => $records->total() === Barber::count());
        }
    }

    public function test_capster_search_and_sorting_still_work(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $selected = Barber::firstOrFail();
        $selected->update(['name' => 'Capster Uji Unik']);
        $this->actingAs($admin)->get(route('admin.resources.index', [
            'resource' => 'capsters', 'q' => 'Capster Uji Unik', 'sort' => 'name', 'direction' => 'desc',
        ]))->assertOk()->assertViewHas('records', fn ($records) =>
            $records->total() === 1 && $records->first()->id === $selected->id);
    }
}
