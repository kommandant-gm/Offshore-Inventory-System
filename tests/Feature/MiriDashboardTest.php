<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_miri_dashboard_reports_live_equipment_metrics(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'miri',
            'permissions' => AccessMatrix::permissionsForRole('miri'),
        ]);
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        foreach ([
            ['branch_id' => $branch->id, 'category' => 'MAJOR EQUIPMENT', 'description' => 'Air compressor', 'tag_no' => 'MRI-001', 'status' => 'In Use', 'current_location' => 'Bintulu', 'active' => true],
            ['branch_id' => $branch->id, 'category' => 'MAJOR EQUIPMENT', 'description' => 'Winch', 'tag_no' => 'MRI-002', 'status' => 'Standby', 'current_location' => 'Miri', 'active' => true],
            ['branch_id' => $branch->id, 'category' => 'MACHINERY', 'description' => 'Pump', 'tag_no' => 'MRI-003', 'status' => 'Under Repair', 'current_location' => 'Miri', 'active' => true],
        ] as $equipment) {
            MajorEquipment::withoutGlobalScopes()->create($equipment);
        }

        $this->actingAs($user)->get(route('major-equipment.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MajorEquipment/Dashboard')
                ->where('summary.total', 3)
                ->where('summary.in_use', 1)
                ->where('summary.standby', 1)
                ->where('summary.under_repair', 1)
                ->where('summary.damaged', 0)
                ->where('categories.0.category', 'Not recorded')
                ->where('categories.0.total', 3)
                ->has('categories.0.statuses', 3)
                ->has('locations', 2)
                ->has('recent', 3));
    }
}
