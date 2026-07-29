<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\KemamanInventoryItem;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KemamanInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_filter_update_and_delete_kemaman_equipment(): void
    {
        [$user, $branch] = $this->userWithKemamanAccess('edit');

        $this->actingAs($user)->post(route('kemaman-inventory.store'), $this->record())
            ->assertRedirect();

        $item = KemamanInventoryItem::firstOrFail();
        $this->assertSame($branch->id, $item->branch_id);
        $this->assertSame('4 SGRSB-HUC-CB20-003', $item->tag_no);
        $this->assertSame(-1, $item->not_traceable_quantity);

        $this->get(route('kemaman-inventory.index', ['search' => 'CB20-003', 'category' => 'Lifting Gear']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('KemamanInventory/Index')
                ->where('summary.records', 1)
                ->where('items.data.0.item_description', '4 Legged Wire Rope Sling 24mm x 3.0mtr')
                ->where('items.data.0.not_traceable_quantity', -1)
                ->where('canEdit', true)
                ->has('items.data', 1));

        $updated = $this->record([
            'available_quantity' => 0,
            'equipment_status' => 'under_inspection',
            'remarks' => 'Pending annual inspection',
        ]);
        $this->patch(route('kemaman-inventory.update', $item), $updated)->assertRedirect();
        $this->assertSame('under_inspection', $item->refresh()->equipment_status);

        $this->delete(route('kemaman-inventory.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('kemaman_inventory_items', ['id' => $item->id]);
    }

    public function test_read_only_member_can_view_but_cannot_change_kemaman_inventory(): void
    {
        [$user, $branch] = $this->userWithKemamanAccess('read');
        $item = KemamanInventoryItem::withoutGlobalScopes()->create([
            ...$this->record(),
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($user)->get(route('kemaman-inventory.index'))->assertOk();
        $this->post(route('kemaman-inventory.store'), $this->record())->assertForbidden();
        $this->patch(route('kemaman-inventory.update', $item), $this->record())->assertForbidden();
        $this->delete(route('kemaman-inventory.destroy', $item))->assertForbidden();
    }

    public function test_kemaman_register_is_only_available_in_the_kemaman_branch_context(): void
    {
        $miri = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'supervisor',
            'permissions' => AccessMatrix::permissionsForRole('supervisor'),
        ]);
        $user->branches()->attach($miri, ['access_level' => 'edit', 'is_default' => true]);

        $this->actingAs($user)->get(route('kemaman-inventory.index'))->assertNotFound();
    }

    private function userWithKemamanAccess(string $level): array
    {
        $branch = Branch::where('code', 'KEMAMAN')->firstOrFail();
        $role = $level === 'read' ? 'viewer' : 'supervisor';
        $user = User::factory()->create([
            'role' => $role,
            'permissions' => AccessMatrix::permissionsForRole($role),
        ]);
        $user->branches()->attach($branch, ['access_level' => $level, 'is_default' => true]);

        return [$user, $branch];
    }

    private function record(array $overrides = []): array
    {
        return [
            'category' => 'Lifting Gear',
            'item_description' => '4 Legged Wire Rope Sling 24mm x 3.0mtr',
            'size_swl' => '15.5MT',
            'unit' => 'EA',
            'tag_no' => '4 SGRSB-HUC-CB20-003',
            'total_quantity' => 1,
            'quantity_in' => 1,
            'quantity_out' => 0,
            'available_quantity' => 1,
            'location_quantity' => 0,
            'damaged_quantity' => 0,
            'beyond_repair_quantity' => 0,
            'not_traceable_quantity' => -1,
            'date_issued' => '2026-07-29',
            'location' => 'TKY',
            'document_reference' => 'COG-1001',
            'backload_date' => null,
            'transfer_reference' => null,
            'certificate_no' => 'CERT-LT-1001',
            'test_expiry_date' => '2027-07-29',
            'equipment_status' => 'available',
            'remarks' => null,
            ...$overrides,
        ];
    }
}
