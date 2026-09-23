<?php
namespace Tests\Feature;
use App\Models\{Branch, MiriPaintItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriPaintDashboardTest extends TestCase
{
    use RefreshDatabase;
    private function staff(): int
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }
    public function test_paint_dashboard_dates_totals_privacy_and_query_isolation(): void
    {
        $branch = $this->staff();
        $this->travelTo(now()->setDate(2026, 9, 11)->startOfDay());
        foreach ([
            ['confirmed','2026-09-10'], ['confirmed','2026-09-11'], ['confirmed','2026-10-11'],
            ['confirmed','2026-10-12'], ['unconfirmed',null], ['not_recorded',null], ['confirmed',null],
        ] as $i => [$status,$date]) MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT',
            'description' => 'Sample', 'batch_no' => $i < 2 ? 'DUP' : 'B'.$i, 'section_2' => 'HEMPEL',
            'current_location' => 'Yard', 'date_status' => $status, 'best_before_date' => $date,
            'manufacture_date' => '2025-01-01', 'unconfirmed_date' => $status === 'unconfirmed' ? '2020-01-01' : null,
            'opening_cans' => 1, 'opening_total_price' => 10, 'source_values' => ['PRIVATE SOURCE']]);
        MiriPaintItem::create(['branch_id' => Branch::where('code','KL-IT')->value('id'), 'category' => 'PRIVATE BRANCH',
            'date_status' => 'confirmed', 'best_before_date' => '2020-01-01', 'opening_total_price' => 9999]);
        DB::enableQueryLog();
        $this->get(route('major-equipment.dashboard', ['view' => 'paint']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('activeDashboard','paint')->where('paintDashboard.summary.total',7)
                ->where('paintDashboard.summary.expired',1)->where('paintDashboard.summary.due_30_days',2)
                ->where('paintDashboard.summary.beyond_30_days',1)->where('paintDashboard.summary.unconfirmed',1)
                ->where('paintDashboard.summary.no_best_before',2)->where('paintDashboard.summary.duplicates',2)
                ->where('paintDashboard.stock.opening_total_price',fn ($v) => (float) $v === 70.0)
                ->has('paintDashboard.attention',3)->where('paintDashboard.attention.0.days_remaining',-1)
                ->where('paintDashboard.attention.1.days_remaining',0)->has('paintDashboard.recent',6)
                ->where('paintDashboard.types.0.total',7)->where('paintDashboard.locations.0.total',7)
                ->missing('constructionDashboard')->missing('rentalDashboard')->missing('summary'))
            ->assertDontSee('PRIVATE SOURCE')->assertDontSee('PRIVATE BRANCH');
        $sql = implode(' ',array_column(DB::getQueryLog(),'query'));
        DB::disableQueryLog();
        foreach (['miri_inventory_items','miri_construction_items','miri_rental_items'] as $table) $this->assertStringNotContainsString($table,$sql);
    }
    public function test_brand_stock_groups_preserve_unknown_and_zero_balances_and_company_scope(): void
    {
        $branch = $this->staff();
        foreach ([['INTERNATION PAINT', 10, 4], [' international paint ', 2.5, 0], ['IP', null, null],
            ['HEMPEL PAINT', 20, 8], ['HEMPEL', null, null], ['Unknown brand', 3, 1]] as [$type, $opening, $closing]) {
            MiriPaintItem::create(['branch_id' => $branch, 'company' => 'DESB', 'category' => 'PAINT',
                'section_2' => $type, 'opening_litres' => $opening, 'balance_litres' => $closing]);
        }
        MiriPaintItem::create(['branch_id' => $branch, 'company' => 'FTSB', 'section_2' => 'IP', 'balance_litres' => 999]);
        MiriPaintItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'),
            'company' => 'DESB', 'section_2' => 'IP', 'balance_litres' => 999]);
        $this->get(route('major-equipment.dashboard', ['view' => 'paint', 'company' => 'DESB']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('paintDashboard.stock.paint_types.ip.records', 3)
                ->where('paintDashboard.stock.paint_types.ip.opening_litres', fn ($v) => (float) $v === 12.5)
                ->where('paintDashboard.stock.paint_types.ip.balance_litres', fn ($v) => (float) $v === 4.0)
                ->where('paintDashboard.stock.paint_types.ip.balance_litres_count', 2)
                ->where('paintDashboard.stock.paint_types.hempel.records', 2)
                ->where('paintDashboard.stock.paint_types.hempel.balance_litres', fn ($v) => (float) $v === 8.0)
                ->where('paintDashboard.stock.paint_types.other.records', 1));
        $this->get(route('paint.index', ['company' => 'DESB', 'section_2' => 'IP']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('stockSummary.paint_types.ip.records', 1)
                ->where('stockSummary.paint_types.ip.balance_litres', null)
                ->where('stockSummary.paint_types.hempel.records', 0)
                ->where('stockSummary.paint_types.hempel.balance_litres', null));
    }

    public function test_empty_dashboard_and_reader_permissions(): void
    {
        $this->staff();
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->get(route('major-equipment.dashboard',['view'=>'paint']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('canEditPaint',false)->where('paintDashboard.summary.total',0)->where('paintDashboard.summary.no_best_before',0)
            ->where('paintDashboard.stock.opening_total_price',null)->has('paintDashboard.attention',0)->has('paintDashboard.recent',0));
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get(route('major-equipment.dashboard',['view'=>'paint']))->assertForbidden();
    }
}
