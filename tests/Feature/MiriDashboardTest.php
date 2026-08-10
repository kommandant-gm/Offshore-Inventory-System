<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\LocationType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_miri_dashboard_reports_live_stock_health_category_and_location_metrics(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'miri',
            'permissions' => AccessMatrix::permissionsForRole('miri'),
        ]);
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $category = Category::create(['code' => 'TOOLS', 'name' => 'Tools', 'type' => CategoryType::Asset, 'active' => true]);
        $location = Location::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'code' => 'MRI-STORE',
            'name' => 'Miri Main Store',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        InventoryItem::withoutGlobalScopes()->create($this->item($branch->id, $category->id, $location->id, 'TOOL-001', 10, 5, 20));
        InventoryItem::withoutGlobalScopes()->create($this->item($branch->id, $category->id, $location->id, 'TOOL-002', 2, 5, 30));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('inventorySummary.active_items', 2)
                ->where('inventorySummary.in_stock', 2)
                ->where('inventorySummary.healthy', 1)
                ->where('inventorySummary.low_stock', 1)
                ->where('inventorySummary.out_of_stock', 0)
                ->where('inventorySummary.total_quantity', 12)
                ->where('inventorySummary.total_value', 260)
                ->where('stockStatus.0.value', 1)
                ->where('stockStatus.1.value', 1)
                ->where('categoryDistribution.0.label', 'Tools')
                ->where('categoryDistribution.0.value', 2)
                ->where('locationDistribution.0.label', 'Miri Main Store')
                ->where('locationDistribution.0.quantity', 12)
                ->has('attentionItems', 1));
    }

    private function item(int $branchId, int $categoryId, int $locationId, string $code, float $opening, float $minimum, float $cost): array
    {
        return [
            'branch_id' => $branchId,
            'item_code' => $code,
            'description' => $code,
            'category_id' => $categoryId,
            'uom' => 'EA',
            'default_location_id' => $locationId,
            'opening_stock' => $opening,
            'standard_cost' => $cost,
            'minimum_stock' => $minimum,
            'active' => true,
        ];
    }
}
