<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Services\{ScaffoldingDashboardService, ScaffoldingStockLedger};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScaffoldingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): int
    {
        $this->withoutVite();
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user = User::factory()->create(['role' => 'miri', 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }

    private function item(int $branch, string $unit = 'PC', ?string $company = 'DESB'): MajorEquipment
    {
        return MajorEquipment::create(['branch_id' => $branch, 'company' => $company, 'inventory_type' => 'machinery', 'section_1' => 'SCAFFOLDING SET', 'section_2' => 'BASE PLATE', 'description' => 'Scaffold '.$unit, 'unit' => $unit]);
    }

    private function opening(MajorEquipment $item, string $yard, int $quantity): void
    {
        $ledger = app(ScaffoldingStockLedger::class);
        $ledger->manual($item->id, $item->branch_id, ['kind' => 'opening', 'yard' => $yard, 'quantity' => $quantity,
            'stock_token' => $ledger->token(null), 'request_key' => (string) Str::uuid(), 'confirmed' => true,
            'document_date' => '2026-10-27', 'note' => 'Physical count'], auth()->id());
    }

    public function test_dashboard_separates_units_yards_and_unverified_stock_and_filters_every_section(): void
    {
        $branch = $this->editor();
        $pc = $this->item($branch); $set = $this->item($branch, 'SET'); $other = $this->item($branch, 'PC', 'FTSB');
        $unassigned = $this->item($branch, 'PC', null);
        $normal = $this->item($branch); $normal->update(['section_1' => 'Machinery']);
        $foreign = $this->item(Branch::where('code', '!=', 'MIRI')->value('id'));
        $this->opening($pc, 'BINTULU', 25); $this->opening($pc, 'LABUAN', 0);
        $this->opening($set, 'BINTULU', 3); $this->opening($other, 'BINTULU', 900);
        \Illuminate\Support\Facades\DB::table('scaffolding_stock_accounts')->insert([
            'equipment_id' => $foreign->id, 'branch_id' => $foreign->branch_id, 'yard' => 'BINTULU',
            'unit' => 'PC', 'opening_quantity' => 700, 'balance' => 700, 'revision' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('major-equipment.dashboard', ['view' => 'scaffolding', 'company' => 'DESB']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('MajorEquipment/Dashboard')->where('activeDashboard', 'scaffolding')
                ->where('companyFilter', 'DESB')->where('scaffoldingDashboard.summary', ['records' => 2, 'verified' => 3, 'unverified' => 1, 'zero_balance' => 1])
                ->has('scaffoldingDashboard.yards.0.balances', 2)->where('scaffoldingDashboard.yards.0.balances.0.unit', 'PC')
                ->where('scaffoldingDashboard.yards.0.balances.0.quantity', fn ($n) => (float) $n === 25.0)
                ->where('scaffoldingDashboard.yards.0.balances.1.unit', 'SET')
                ->has('scaffoldingDashboard.recent', 3)->has('scaffoldingDashboard.needsVerification', 1)
                ->where('scaffoldingDashboard.needsVerification.0.id', $set->id)->where('scaffoldingDashboard.subcategories.0.total', 2));
        $all = app(ScaffoldingDashboardService::class)->data();
        $this->assertSame(4, $all['summary']['records']);
        $this->assertSame(4, $all['summary']['unverified']);
        $unassignedData = app(ScaffoldingDashboardService::class)->data('unassigned');
        $this->assertSame(1, $unassignedData['summary']['records']);
        $this->assertSame($unassigned->id, $unassignedData['needsVerification']->first()->id);
        $this->assertCount(0, $unassignedData['recent']);
    }

    public function test_empty_dashboard_is_available_without_edit_permission(): void
    {
        $this->editor();
        $user = auth()->user(); $permissions = $user->permissions; $permissions['assets'] = 'read';
        $user->update(['permissions' => $permissions]); $this->actingAs($user->fresh());
        $this->get(route('major-equipment.dashboard', ['view' => 'scaffolding']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('scaffoldingDashboard.summary.records', 0)->where('scaffoldingDashboard.summary.unverified', 0)
            ->has('scaffoldingDashboard.yards', 2)->has('scaffoldingDashboard.recent', 0)->has('scaffoldingDashboard.needsVerification', 0));
        $permissions['assets'] = 'none'; $user->update(['permissions' => $permissions]); $this->actingAs($user->fresh());
        $this->get(route('major-equipment.dashboard', ['view' => 'scaffolding']))->assertForbidden();
    }
}
