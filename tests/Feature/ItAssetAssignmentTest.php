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

class ItAssetAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_edit_checkout_reassign_and_check_in_an_asset(): void
    {
        [$user, $asset] = $this->editorAndAsset();
        $target = User::factory()->create(['name' => 'Target User', 'username' => 'EMP-100']);
        $target->branches()->attach($asset->branch_id, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($user)
            ->get(route('it-assets.edit', $asset))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItAssets/Edit')
                ->where('asset.asset_tag_no', 'KL-TEST-001'));

        $this->post(route('it-assets.checkout', $asset), [
            'user_id' => $target->id,
            'assigned_at' => '2026-07-21',
            'department' => 'IT',
        ])->assertRedirect();

        $asset->refresh()->load('currentAssignment');
        $this->assertSame('deployed', $asset->current_status->value);
        $this->assertSame('Target User', $asset->currentAssignment->assigned_to_name);
        $this->assertSame('EMP-100', $asset->currentAssignment->employee_id);

        $this->post(route('it-assets.checkout', $asset), [
            'assigned_to_name' => 'Another User',
            'employee_id' => 'EMP-200',
            'assigned_at' => '2026-07-21',
            'department' => 'QHSE',
        ])->assertRedirect();

        $asset->refresh()->load(['assignments', 'currentAssignment']);
        $this->assertCount(2, $asset->assignments);
        $this->assertSame('Another User', $asset->currentAssignment->assigned_to_name);
        $this->assertNotNull($asset->assignments->firstWhere('assigned_to_name', 'Target User')->returned_at);

        $this->patch(route('it-assets.check-in', $asset))->assertRedirect();

        $asset->refresh()->load('currentAssignment');
        $this->assertSame('available', $asset->current_status->value);
        $this->assertNull($asset->currentAssignment);
        $this->assertDatabaseCount('asset_assignments', 2);
    }

    public function test_read_only_user_cannot_change_asset_assignments(): void
    {
        [, $asset, $branch] = $this->editorAndAsset();
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'permissions' => AccessMatrix::permissionsForRole('viewer'),
        ]);
        $viewer->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($viewer)->post(route('it-assets.checkout', $asset), [
            'assigned_to_name' => 'Blocked User',
            'assigned_at' => '2026-07-21',
        ])->assertForbidden();
    }

    private function editorAndAsset(): array
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $category = Category::create(['code' => 'TEST-ASSET', 'name' => 'Test Asset', 'type' => 'asset', 'active' => true]);
        $asset = Asset::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'asset_tag_no' => 'KL-TEST-001',
            'description' => 'Test laptop',
            'category_id' => $category->id,
            'current_status' => 'available',
            'current_condition' => 'good',
            'active' => true,
        ]);

        return [$user, $asset, $branch];
    }
}
