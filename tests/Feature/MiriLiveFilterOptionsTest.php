<?php
namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiriLiveFilterOptionsTest extends TestCase
{
    use RefreshDatabase;
    public function test_preview_returns_scoped_choices_only_for_every_register_and_apply_still_returns_records(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        foreach ([
            [MajorEquipment::class, 'major-equipment.index', 'locationOptions', 'equipment', ['inventory_type' => 'machinery']],
            [MajorEquipment::class, 'major-equipment.index', 'locationOptions', 'equipment', ['inventory_type' => 'cargo']],
            [MiriRentalItem::class, 'miri-rental.index', 'locationOptions', 'rentals', []],
            [MiriConstructionItem::class, 'construction.index', 'options.location', 'records', []],
            [MiriPaintItem::class, 'paint.index', 'options.location', 'records', []],
        ] as [$class, $route, $locationKey, $recordsKey, $extra]) {
            $base = ['branch_id' => $branch, 'company' => 'DESB', 'description' => 'Match', 'category' => 'PPE', 'section_1' => 'CONSUMABLE', 'current_location' => 'BTU', ...$extra];
            $class::create($base);
            $class::create([...$base, 'category' => 'TEC', 'current_location' => 'LBN']);
            $class::create([...$base, 'company' => 'FTSB', 'current_location' => 'EXCLUDED COMPANY']);
            $class::create([...$base, 'branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'current_location' => 'PRIVATE']);
            $filters = ['company' => 'DESB', 'category' => 'PPE', 'search' => 'Match', ...$extra];
            $this->getJson(route($route, [...$filters, 'filter_options' => 1]))->assertOk()->assertJsonPath($locationKey, ['BTU'])
                ->assertJsonMissingPath($recordsKey)->assertJsonMissingPath('summary')->assertJsonMissingPath('stockSummary');
            $this->get(route($route, $filters))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p->where($recordsKey.'.total', 1));
            $this->getJson(route($route, [...$filters, 'search' => 'no matching row', 'filter_options' => 1]))->assertOk()->assertJsonPath($locationKey, []);
        }
        // Preview must not trigger paint month rollover or change stored stock.
        $paint = MiriPaintItem::firstOrFail();
        $paint->update(['stock_period' => '2020-01-01', 'balance_litres' => 5, 'opening_litres' => 10]);
        $this->getJson(route('paint.index', ['filter_options' => 1]))->assertOk();
        $this->assertSame('2020-01-01', $paint->fresh()->stock_period);
        $this->assertDatabaseCount('miri_paint_stock_months', 0);
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['major-equipment.index', 'miri-rental.index', 'construction.index', 'paint.index'] as $route) {
            $this->getJson(route($route, ['filter_options' => 1]))->assertForbidden();
        }
    }
}
