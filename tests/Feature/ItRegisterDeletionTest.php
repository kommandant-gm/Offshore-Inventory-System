<?php

namespace Tests\Feature;

use App\Models\{Asset, Branch, Category, ItLicense, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItRegisterDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_delete_confirmed_records_and_deletion_is_audited(): void
    {
        [$user, $records] = $this->records('edit');
        $asset = $records['it-assets'];
        $assignment = $asset->assignments()->create([
            'assigned_to_name' => 'Test User', 'assigned_at' => today(), 'assigned_by' => $user->id,
        ]);
        foreach ($records as $register => $record) {
            $this->actingAs($user)->from(route("{$register}.index"))
                ->delete(route("{$register}.destroy", $record), ['confirmed' => true])
                ->assertRedirect(route("{$register}.index"))->assertSessionHas('success');
            $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
            $this->assertDatabaseHas('audit_logs', [
                'event' => 'deleted', 'auditable_type' => $record->getMorphClass(), 'auditable_id' => $record->id,
            ]);
        }
        $this->assertDatabaseMissing('asset_assignments', ['id' => $assignment->id]);
    }

    public function test_deletion_requires_confirmation(): void
    {
        [$user, $records] = $this->records('edit');
        foreach ($records as $register => $record) {
            $this->actingAs($user)->delete(route("{$register}.destroy", $record))
                ->assertSessionHasErrors('confirmed');
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
        }
    }

    public function test_viewer_cannot_delete_records(): void
    {
        [$user, $records] = $this->records('read');
        foreach ($records as $register => $record) {
            $this->actingAs($user)->delete(route("{$register}.destroy", $record), ['confirmed' => true])->assertForbidden();
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
        }
    }

    public function test_editor_cannot_delete_another_branchs_records(): void
    {
        [$user, $records] = $this->records('edit');
        $otherBranch = Branch::where('code', 'MIRI')->firstOrFail();
        foreach ($records as $register => $record) {
            $record->update(['branch_id' => $otherBranch->id]);
            $this->actingAs($user)->delete(route("{$register}.destroy", $record), ['confirmed' => true])->assertNotFound();
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
        }
    }

    private function records(string $level): array
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $permissions = AccessMatrix::permissionsForRole('supervisor');
        $permissions['it_assets'] = $level;
        $user = User::factory()->create(['role' => 'supervisor', 'permissions' => $permissions]);
        $user->branches()->attach($branch, ['access_level' => $level, 'is_default' => true]);
        $category = Category::create(['code' => 'DELETE-TEST', 'name' => 'Laptop', 'type' => 'asset', 'active' => true]);

        return [$user, [
            'it-assets' => Asset::withoutGlobalScopes()->create([
                'branch_id' => $branch->id, 'asset_tag_no' => 'DELETE-ASSET', 'description' => 'Test laptop',
                'category_id' => $category->id, 'current_status' => 'available', 'active' => true,
            ]),
            'it-licenses' => ItLicense::withoutGlobalScopes()->create([
                'branch_id' => $branch->id, 'license_code' => 'DELETE-LICENCE', 'software_name' => 'Test software',
                'license_type' => 'perpetual', 'seats_total' => 1, 'seats_assigned' => 0, 'active' => true,
            ]),
        ]];
    }
}
