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
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_from_kemaman_to_kl_redirects_directly_to_the_requested_kl_page(): void
    {
        $kemaman = Branch::where('code', 'KEMAMAN')->firstOrFail();
        $kl = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'supervisor',
            'permissions' => AccessMatrix::permissionsForRole('supervisor'),
        ]);
        $user->branches()->attach($kemaman, ['access_level' => 'edit', 'is_default' => true]);
        $user->branches()->attach($kl, ['access_level' => 'edit', 'is_default' => false]);

        $this->actingAs($user)
            ->from(route('kemaman-inventory.dashboard'))
            ->patch(route('branches.activate'), [
                'branch_id' => $kl->id,
                'destination_route' => 'it-assets.dashboard',
            ])
            ->assertRedirect(route('it-assets.dashboard'));

        $this->assertSame($kl->id, session('branch_id'));
        $this->get(route('it-assets.dashboard'))->assertOk();
    }

    public function test_miri_user_cannot_list_or_open_kl_inventory(): void
    {
        [$miri, $kl] = [Branch::where('code', 'MIRI')->firstOrFail(), Branch::where('code', 'KL-IT')->firstOrFail()];
        $user = User::factory()->create(['username' => 'mariesim', 'department' => 'PROJECT', 'role' => 'miri', 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($miri, ['access_level' => 'read', 'is_default' => true]);
        $category = Category::create(['code' => 'TEST', 'name' => 'Test', 'type' => CategoryType::Asset, 'active' => true]);
        $miriLocation = Location::withoutGlobalScopes()->create(['branch_id' => $miri->id, 'code' => 'MRI-T', 'name' => 'Miri Test', 'type' => LocationType::Yard, 'active' => true]);
        $klLocation = Location::withoutGlobalScopes()->create(['branch_id' => $kl->id, 'code' => 'KL-T', 'name' => 'KL Test', 'type' => LocationType::Yard, 'active' => true]);
        $miriItem = InventoryItem::withoutGlobalScopes()->create($this->item($miri->id, $miriLocation->id, 'MRI-ITEM'));
        $klItem = InventoryItem::withoutGlobalScopes()->create($this->item($kl->id, $klLocation->id, 'KL-ITEM'));

        $this->actingAs($user)->get(route('assets.index'))->assertOk()->assertDontSee('KL-ITEM')->assertSee('MRI-ITEM');
        $this->actingAs($user)->get(route('assets.show', $klItem))->assertNotFound();
        $this->actingAs($user)->get(route('assets.show', $miriItem))->assertOk();
    }

    public function test_read_only_miri_membership_blocks_edit_when_kl_staff_switches_branch(): void
    {
        $miri = Branch::where('code', 'MIRI')->firstOrFail();
        $kl = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create(['role' => 'supervisor', 'permissions' => AccessMatrix::permissionsForRole('supervisor')]);
        $user->branches()->attach($kl, ['access_level' => 'edit', 'is_default' => true]);
        $user->branches()->attach($miri, ['access_level' => 'read', 'is_default' => false]);
        $this->actingAs($user)->patch(route('branches.activate'), ['branch_id' => $miri->id])->assertRedirect();
        $this->assertFalse($user->canEdit('assets'));
        $this->actingAs($user)->get(route('assets.create'))->assertForbidden();
    }

    public function test_miri_inventory_role_can_open_stock_item_and_cog_creation_in_miri(): void
    {
        $miri = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'miri',
            'permissions' => AccessMatrix::permissionsForRole('miri'),
        ]);
        $user->branches()->attach($miri, ['access_level' => 'edit', 'is_default' => true]);

        $this->actingAs($user)->get(route('assets.create'))->assertOk();
        $this->actingAs($user)->get(route('cogs.create'))->assertOk();
    }

    private function item(int $branchId, int $locationId, string $code): array
    {
        return ['branch_id' => $branchId, 'item_code' => $code, 'description' => $code, 'category_id' => Category::first()->id, 'uom' => 'EA', 'default_location_id' => $locationId, 'opening_stock' => 1, 'standard_cost' => 1, 'active' => true];
    }
}
