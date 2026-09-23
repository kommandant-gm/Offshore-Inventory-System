<?php
namespace Tests\Feature;

use App\Models\{Branch, MiriCog, MiriPaintItem, User};
use App\Services\PaintStockLedger;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaintStockLedgerTest extends TestCase
{
    use RefreshDatabase;
    private function setupStock(): MiriPaintItem
    {
        $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-09-23 12:00:00', 'Asia/Kuala_Lumpur'));
        $user = User::factory()->create(['role'=>'miri','directory_active'=>true,'permissions'=>AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code','MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level'=>'edit','is_default'=>true]);
        $this->actingAs($user);
        return MiriPaintItem::create(['branch_id'=>$branch,'category'=>'PAINT','description'=>'Paint','opening_litres'=>12,'balance_litres'=>10,'balance_cans'=>2]);
    }
    private function payload(MiriPaintItem $item, string $movement = 'Issue out', string $qty = '1.250', string $unit = 'LTR'): array
    {
        return ['movement_type'=>$movement,'document_date'=>'2026-08-01','issued_by_name'=>'Storekeeper',
            'items'=>[['item_type'=>'Paint','item_id'=>$item->id,'quantity'=>$qty,'unit'=>$unit]]];
    }
    public function test_issue_is_atomic_idempotent_and_cancellation_restores_stock(): void
    {
        $item = $this->setupStock();
        $this->post(route('miri-cogs.store'), $this->payload($item))->assertSessionHasNoErrors();
        $cog = MiriCog::latest('id')->firstOrFail();
        $this->assertSame('8.750', $item->fresh()->balance_litres);
        $this->assertSame('2.000', $item->fresh()->balance_cans);
        app(PaintStockLedger::class)->post($cog->items->first(), auth()->id());
        $this->assertSame('8.750', $item->fresh()->balance_litres);
        $this->assertDatabaseCount('miri_paint_stock_movements', 1);
        $this->assertDatabaseHas('miri_paint_stock_movements', ['kind'=>'outbound','period'=>'2026-09-01']);
        $this->post(route('miri-cogs.cancel', $cog), ['reason'=>'Not dispatched'])->assertSessionHasNoErrors();
        $this->assertSame('10.000', $item->fresh()->balance_litres);
        app(PaintStockLedger::class)->post($cog->items->first(), auth()->id(), true);
        $this->assertDatabaseCount('miri_paint_stock_movements', 2);
        $payload = $this->payload($item, 'Issue out', '6');
        $payload['items'][] = $payload['items'][0];
        $this->post(route('miri-cogs.store'), $payload)->assertSessionHasErrors('items');
        $this->assertSame('10.000', $item->fresh()->balance_litres);
        $this->assertDatabaseCount('miri_cogs', 1);
        $this->assertDatabaseCount('miri_paint_stock_movements', 2);
    }
    public function test_dashboard_quantities_group_locations_and_count_only_current_uncancelled_litre_issues(): void
    {
        $item = $this->setupStock();
        $item->update(['company' => 'DESB', 'section_2' => 'INTERNATION PAINT', 'current_location' => 'BINTULU PAINT STORE - RACK 4C', 'issue_litres' => 500]);
        $this->post(route('miri-cogs.store'), $this->payload($item))->assertSessionHasNoErrors();
        $cancelled = MiriCog::latest('id')->firstOrFail();
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Issue out', '2'))->assertSessionHasNoErrors();
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Issue out', '0.5'))->assertSessionHasNoErrors();
        DB::table('miri_paint_stock_movements')->where('cog_item_id', MiriCog::latest('id')->firstOrFail()->items->first()->id)
            ->update(['period' => '2026-08-01']);
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Transfer', '1'))->assertSessionHasNoErrors();
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Issue out', '1', 'CAN'))->assertSessionHasNoErrors();
        $this->post(route('miri-cogs.cancel', $cancelled), ['reason' => 'Not dispatched'])->assertSessionHasNoErrors();
        foreach ([['BTU', 'IP', 4], ['LBN PAINT STORE', 'HEMPEL PAINT', 8], ['LABUAN STORE', 'HEMPEL', 0],
            ['SKA', 'IP', 2], ['SBA', 'IP', null], ['Unknown', 'IP', 50]] as [$location, $type, $stock]) {
            MiriPaintItem::create(['branch_id' => $item->branch_id, 'company' => 'DESB', 'category' => 'PAINT',
                'current_location' => $location, 'section_2' => $type, 'balance_litres' => $stock]);
        }
        MiriPaintItem::create(['branch_id' => $item->branch_id, 'company' => 'FTSB', 'current_location' => 'BTU', 'balance_litres' => 999]);
        MiriPaintItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'company' => 'DESB', 'current_location' => 'BTU', 'balance_litres' => 999]);
        $this->get(route('major-equipment.dashboard', ['view' => 'paint', 'company' => 'DESB']))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->where('paintDashboard.quantities.period', '2026-09-01')
                ->where('paintDashboard.quantities.types.0.label', 'IP Paint')
                ->where('paintDashboard.quantities.types.0.locations.0.issued', fn ($v) => (float) $v === 2.0)
                ->where('paintDashboard.quantities.types.0.locations.0.stock', fn ($v) => (float) $v === 10.5)
                ->where('paintDashboard.quantities.locations.0.records', 2)
                ->where('paintDashboard.quantities.locations.1.stock', fn ($v) => (float) $v === 8.0)
                ->where('paintDashboard.quantities.locations.1.recorded', 2)
                ->where('paintDashboard.quantities.locations.2.stock', fn ($v) => (float) $v === 2.0)
                ->where('paintDashboard.quantities.locations.3.stock', null)
                ->where('paintDashboard.quantities.excluded_records', 1));
    }

    public function test_backloads_are_bounded_by_tracked_outbound_and_units_stay_separate(): void
    {
        $item = $this->setupStock();
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Received backload'))->assertSessionHasErrors('items');
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Transfer', '3'))->assertSessionHasNoErrors();
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Received backload', '2'))->assertSessionHasNoErrors();
        $this->assertSame('9.000', $item->fresh()->balance_litres);
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Received backload', '2'))->assertSessionHasErrors('items');
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Received backload', '1', 'CAN'))->assertSessionHasErrors('items');
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Return to supplier', '1', 'CAN'))->assertSessionHasNoErrors();
        $this->assertSame('1.000', $item->fresh()->balance_cans);
        $item->update(['balance_cans'=>null]);
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Issue out', '1', 'CAN'))->assertSessionHasErrors('items');
    }
    public function test_rollover_preserves_months_catches_up_and_runs_once(): void
    {
        $item = $this->setupStock();
        $item->update(['balance_cans'=>null]);
        $this->post(route('miri-cogs.store'), $this->payload($item, 'Issue out', '2'))->assertSessionHasNoErrors();
        $cog = MiriCog::latest('id')->firstOrFail();
        $this->travelTo(\Carbon\Carbon::parse('2026-12-01 00:01:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('paint:rollover')->assertSuccessful();
        $this->artisan('paint:rollover')->assertSuccessful();
        $this->assertSame('8.000', $item->fresh()->opening_litres);
        $this->assertNull($item->fresh()->opening_cans);
        $this->assertSame('2026-12-01', $item->fresh()->stock_period);
        $this->assertDatabaseCount('miri_paint_stock_months', 3);
        $this->assertDatabaseHas('miri_paint_stock_months', ['period'=>'2026-09-01','opening_litres'=>12,'closing_litres'=>8]);
        $this->assertDatabaseHas('miri_paint_stock_months', ['period'=>'2026-10-01','opening_litres'=>8,'closing_litres'=>8]);
        $this->post(route('miri-cogs.cancel', $cog), ['reason'=>'Never dispatched'])->assertSessionHasNoErrors();
        $this->assertSame('10.000', $item->fresh()->balance_litres);
        $this->assertSame('8.000', $item->fresh()->opening_litres);
        $this->assertDatabaseHas('miri_paint_stock_months', ['period'=>'2026-09-01','closing_litres'=>8]);
        $this->assertDatabaseHas('miri_paint_stock_movements', ['kind'=>'cancellation','period'=>'2026-12-01']);
        $this->get(route('paint.show', $item))->assertOk();
    }
    public function test_adjustment_requires_current_stock_token_and_note(): void
    {
        $item = $this->setupStock();
        $token = app(PaintStockLedger::class)->token($item);
        $data = ['category'=>'PAINT','description'=>'Paint','date_status'=>'not_recorded','balance_litres'=>15,'stock_token'=>$token];
        $this->patch(route('paint.update', $item), $data)->assertSessionHasErrors('review_note');
        $this->post(route('miri-cogs.store'), $this->payload($item))->assertSessionHasNoErrors();
        $this->patch(route('paint.update', $item), $data + ['review_note'=>'Receipt checked'])->assertSessionHasErrors('stock_token');
        $data['stock_token'] = app(PaintStockLedger::class)->token($item->fresh());
        $this->patch(route('paint.update', $item), $data + ['review_note'=>'Receipt checked'])->assertSessionHasNoErrors();
        $this->assertSame('15.000', $item->fresh()->balance_litres);
        $this->assertDatabaseHas('miri_paint_stock_movements', ['kind'=>'adjustment','note'=>'Receipt checked','balance_before'=>8.75,'balance_after'=>15]);
    }
    public function test_legacy_cancellation_does_not_add_stock_and_foreign_branch_is_rejected(): void
    {
        $item = $this->setupStock();
        $legacy = MiriCog::create(['branch_id'=>$item->branch_id,'cog_no'=>'LEGACY','movement_type'=>'Issue out','document_date'=>'2026-08-01','status'=>'draft']);
        $legacy->items()->create(['branch_id'=>$item->branch_id,'item_type'=>'Paint','item_id'=>$item->id,'quantity'=>5,'unit'=>'LTR']);
        $this->post(route('miri-cogs.cancel', $legacy), ['reason'=>'Never dispatched'])->assertSessionHasNoErrors();
        $this->assertSame('10.000', $item->fresh()->balance_litres);
        $other = MiriPaintItem::create(['branch_id'=>Branch::where('code','KL-IT')->value('id'),'balance_litres'=>10]);
        $this->post(route('miri-cogs.store'), $this->payload($other))->assertNotFound();
        $this->assertDatabaseCount('miri_paint_stock_movements', 0);
    }
}
