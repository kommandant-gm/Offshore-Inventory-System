<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItAssetRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_send_an_asset_for_repair_and_return_it_to_service(): void
    {
        [$user, $asset] = $this->editorAndAsset();

        $this->actingAs($user)->post(route('it-assets.repairs.store'), [
            'asset_id' => $asset->id,
            'movement_date' => '2026-07-28',
            'handled_by' => 'HP Service Centre',
            'reference_no' => 'JOB-1001',
            'remarks' => 'Display flickers intermittently.',
        ])->assertRedirect();

        $asset->refresh();
        $this->assertSame('under_repair', $asset->current_status->value);
        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'movement_type' => 'send_for_repair',
            'from_status' => 'available',
            'to_status' => 'under_repair',
            'handled_by' => 'HP Service Centre',
            'reference_no' => 'JOB-1001',
        ]);

        $this->actingAs($user)->get(route('it-assets.repairs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItAssets/Section')
                ->where('repairMode', true)
                ->has('rows', 1)
                ->where('rows.0.asset_tag', 'KL-REPAIR-001')
                ->where('rows.0.handled_by', 'HP Service Centre'));

        $this->actingAs($user)->patch(route('it-assets.repairs.return', $asset), [
            'movement_date' => '2026-07-28',
            'condition_after' => 'good',
            'remarks' => 'Display cable replaced and tested.',
        ])->assertRedirect();

        $this->assertSame('available', $asset->fresh()->current_status->value);
        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'movement_type' => 'return_from_repair',
            'to_status' => 'available',
        ]);
    }

    public function test_assigned_asset_must_be_checked_in_before_repair(): void
    {
        [$user, $asset, $branch] = $this->editorAndAsset();
        $asset->assignments()->create([
            'branch_id' => $branch->id,
            'assigned_to_name' => 'Staff Member',
            'assigned_at' => '2026-07-20',
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)->from(route('it-assets.show', $asset))->post(route('it-assets.repairs.store'), [
            'asset_id' => $asset->id,
            'movement_date' => '2026-07-28',
            'remarks' => 'Broken keyboard.',
        ])->assertRedirect(route('it-assets.show', $asset))->assertSessionHasErrors('asset_id');

        $this->assertSame('available', $asset->fresh()->current_status->value);
        $this->assertDatabaseCount('asset_movements', 0);
    }

    public function test_read_only_user_cannot_change_repair_status(): void
    {
        [, $asset, $branch] = $this->editorAndAsset();
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'permissions' => AccessMatrix::permissionsForRole('viewer'),
        ]);
        $viewer->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($viewer)->post(route('it-assets.repairs.store'), [
            'asset_id' => $asset->id,
            'movement_date' => '2026-07-28',
            'remarks' => 'Should not be accepted.',
        ])->assertForbidden();
    }

    private function editorAndAsset(): array
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $category = Category::create(['code' => 'REPAIR-TEST', 'name' => 'Repair Test', 'type' => 'asset', 'active' => true]);
        $asset = Asset::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'asset_tag_no' => 'KL-REPAIR-001',
            'description' => 'Test laptop',
            'category_id' => $category->id,
            'current_status' => 'available',
            'current_condition' => 'good',
            'active' => true,
        ]);

        return [$user, $asset, $branch];
    }
}
