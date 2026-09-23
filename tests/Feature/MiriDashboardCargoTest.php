<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriDashboardCargoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_filters_equipment_certificates_and_statuses_by_type_and_branch(): void
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $miri = Branch::where('code', 'MIRI')->firstOrFail();
        $user->branches()->attach($miri, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        foreach ([['cargo','NO CERT','DNV'],['cargo',null,'SLING'],['machinery','Standby','AIR WINCH']] as [$type,$status,$section]) {
            $item = MajorEquipment::create(['branch_id' => $miri->id, 'inventory_type' => $type, 'category' => 'MAJOR EQUIPMENT', 'section_2' => $section, 'status' => $status, 'current_location' => $type === 'cargo' ? 'Marine' : 'Miri']);
            $item->certificates()->create(['branch_id' => $miri->id, 'certificate_type' => 'TEST', 'expiry_date' => today()->addDays(5)]);
        }
        MajorEquipment::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'inventory_type' => 'cargo', 'category' => 'MAJOR EQUIPMENT', 'status' => 'Private']);
        $url = route('major-equipment.dashboard');
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component('MajorEquipment/Dashboard')->where('summary.total', 3)->where('expiry.due_30_days', 3)->has('categories', 3)->has('statusBreakdown', 3)->where('typeCounts.cargo', 2)->where('inventoryType', 'all'));
        $this->get($url.'?inventory_type=cargo')->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.total', 2)->where('summary.standby', 0)->where('expiry.due_30_days', 2)->has('expiring', 2)->has('recent', 2)->has('categories', 2)->has('locations', 1)->where('locations.0.label', 'Marine')->has('statusBreakdown', 2)->where('statusBreakdown', fn ($rows) => collect($rows)->sum('value') === 2 && collect($rows)->pluck('label')->contains('Not recorded')));
        $this->get($url.'?inventory_type=machinery')->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.total', 1)->where('summary.standby', 1)->where('expiry.due_30_days', 1)->has('recent', 1)->where('categories.0.category', 'AIR WINCH'));
        $this->get($url.'?inventory_type=cargo&view=rentals')->assertOk()->assertInertia(fn (Assert $p) => $p->where('activeDashboard', 'rentals')->has('rentalDashboard'));
    }

    public function test_subcategory_status_counts_preserve_other_statuses_and_filter_by_type(): void
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $miri = Branch::where('code', 'MIRI')->firstOrFail();
        $user->branches()->attach($miri, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        foreach ([
            ['machinery', 'In Use', ' WINCH '],
            ['machinery', 'Standby', 'WINCH'],
            ['machinery', 'Under Repair', 'WINCH'],
            ['machinery', 'PENDING REPAIR', 'WINCH'],
            ['machinery', 'Damaged', 'WINCH'],
            ['machinery', null, 'WINCH'],
            ['cargo', 'NO CERT', 'WINCH'],
            ['cargo', ' ', null],
            ['cargo', null, ' '],
        ] as [$type, $status, $subcategory]) {
            MajorEquipment::create(['branch_id' => $miri->id, 'inventory_type' => $type, 'category' => 'MAJOR EQUIPMENT', 'section_2' => $subcategory, 'status' => $status]);
        }
        MajorEquipment::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'inventory_type' => 'machinery', 'category' => 'MAJOR EQUIPMENT', 'section_2' => 'WINCH', 'status' => 'Private']);

        $url = route('major-equipment.dashboard');
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.total', 9)->has('categories', 2)
            ->where('categories.0.category', 'WINCH')->where('categories.0.total', 7)
            ->where('categories.0.statuses', fn ($rows) => collect($rows)->sum('value') === 7
                && collect($rows)->pluck('label')->sort()->values()->all() === ['Damaged', 'In Use', 'NO CERT', 'Not recorded', 'PENDING REPAIR', 'Standby', 'Under Repair'])
            ->where('categories.1.category', 'Not recorded')->where('categories.1.total', 2)
            ->where('categories.1.statuses.0.label', 'Not recorded')->where('categories.1.statuses.0.value', 2));
        $this->get($url.'?inventory_type=machinery')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('categories', 1)->where('categories.0.total', 6)
            ->where('categories.0.statuses', fn ($rows) => collect($rows)->sum('value') === 6 && ! collect($rows)->pluck('label')->contains('NO CERT')));
        $this->get($url.'?inventory_type=cargo')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('categories', 2)->where('categories.0.category', 'Not recorded')->where('categories.0.total', 2)
            ->where('categories.1.category', 'WINCH')->where('categories.1.total', 1));
    }

}
