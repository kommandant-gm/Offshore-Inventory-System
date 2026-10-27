<?php
namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, MiriCog, MiriConstructionItem, User};
use App\Services\ScaffoldingStockLedger;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScaffoldingStockTest extends TestCase
{
    use RefreshDatabase;
    private function item(): MajorEquipment
    {
        if (! auth()->check()) {
            $this->withoutVite();
            $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
            $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
            $this->actingAs($user);
        }
        return MajorEquipment::create(['branch_id' => Branch::where('code', 'MIRI')->value('id'), 'inventory_type' => 'machinery', 'category' => 'MAJOR EQUIPMENT', 'section_1' => 'SCAFFOLDING SET', 'section_2' => 'BASE PLATE', 'description' => 'BASE PLATE', 'unit' => 'PC', 'quantity' => 999]);
    }
    private function data($item, $kind = 'opening', $quantity = 100, $yard = 'BINTULU'): array
    {
        return ['kind' => $kind, 'yard' => $yard, 'quantity' => $quantity, 'stock_token' => app(ScaffoldingStockLedger::class)->token(DB::table('scaffolding_stock_accounts')->where('equipment_id', $item->id)->where('yard', $yard)->first()), 'request_key' => (string) Str::uuid(), 'confirmed' => true, 'document_date' => '2026-10-27', 'note' => 'Verified physical count', 'reference' => '', 'contract' => '', 'destination' => '', 'mr_reference' => ''];
    }
    private function postStock($item, array $data) { return $this->post(route('scaffolding.stock.store', $item), $data); }
    private function balance($item, $yard = 'BINTULU'): float { return (float) DB::table('scaffolding_stock_accounts')->where('equipment_id', $item->id)->where('yard', $yard)->value('balance'); }
    private function cog($item, $quantity = 10, $movement = 'Issue out', array $extra = []): MiriCog
    {
        $this->post(route('miri-cogs.store'), ['movement_type' => $movement, 'document_date' => '2026-10-27', 'issued_by_name' => 'Storekeeper', 'destination' => 'LABUAN', 'items' => [['item_type' => 'Major equipment', 'item_id' => $item->id, 'unit' => 'PC', 'quantity' => $quantity, 'scaffolding_yard' => 'BINTULU', 'scaffolding_contract' => 'SHELL'], ...$extra]])->assertSessionHasNoErrors()->assertRedirect();
        return MiriCog::latest('id')->firstOrFail();
    }
    private function confirm($cog) { return $this->post(route('miri-cogs.confirm-stock', $cog), ['confirmed' => true]); }

    public function test_opening_is_explicit_idempotent_and_protects_against_stale_writes(): void
    {
        $item = $this->item();
        $this->assertDatabaseCount('scaffolding_stock_accounts', 0);
        $data = $this->data($item);
        $this->postStock($item, [...$data, 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->postStock($item, $data)->assertSessionHasNoErrors();
        $this->postStock($item, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('scaffolding_stock_movements', 1);
        $stale = $this->data($item, 'receipt', 5);
        $this->postStock($item, $this->data($item, 'receipt', 20))->assertSessionHasNoErrors();
        $this->postStock($item, $stale)->assertSessionHasErrors('stock');
        $this->assertSame(120.0, $this->balance($item));
        $this->assertEquals(999, $item->fresh()->quantity);
        $this->get(route('major-equipment.show', $item))->assertOk();
        $this->delete(route('miri-register.destroy', ['register' => 'major', 'item' => $item->id]), ['confirmed' => true])->assertSessionHasErrors('deletion');
    }
    public function test_multiple_issues_share_yard_stock_across_contracts_and_roll_back_overdraw(): void
    {
        $item = $this->item(); $this->postStock($item, $this->data($item))->assertSessionHasNoErrors();
        $this->postStock($item, $this->data($item, 'opening', 20, 'LABUAN'))->assertSessionHasNoErrors();
        $row = ['quantity' => 30, 'destination' => 'Project A', 'contract' => 'SHELL', 'reference' => 'EXT-1', 'document_date' => '2026-10-27', 'note' => 'Issued', 'mr_reference' => 'MR1'];
        $data = [...$this->data($item, 'issue'), 'rows' => [$row, [...$row, 'quantity' => 80, 'contract' => 'SKA', 'reference' => 'EXT-2']]];
        $this->postStock($item, $data)->assertSessionHasErrors('stock');
        $this->assertSame(100.0, $this->balance($item));
        $data['rows'][1]['quantity'] = 10;
        $this->postStock($item, $data)->assertSessionHasNoErrors();
        $this->postStock($item, $data)->assertSessionHasNoErrors();
        $this->assertSame(60.0, $this->balance($item)); $this->assertSame(20.0, $this->balance($item, 'LABUAN'));
        $this->postStock($item, [...$this->data($item, 'issue'), 'rows' => [$row]])->assertSessionHasErrors('stock');
        $this->postStock($item, [...$this->data($item, 'backload', 41), 'reference' => 'RET1'])->assertSessionHasErrors('stock');
        $this->postStock($item, [...$this->data($item, 'backload', 5), 'reference' => 'RET1'])->assertSessionHasNoErrors();
        $this->assertSame(65.0, $this->balance($item));
    }
    public function test_drafts_do_not_deduct_and_confirmation_is_atomic_and_idempotent(): void
    {
        $item = $this->item(); $cog = $this->cog($item, 60);
        $this->confirm($cog)->assertSessionHasErrors('stock');
        $this->postStock($item, $this->data($item))->assertSessionHasNoErrors();
        $other = $this->cog($item, 50);
        $this->assertSame(100.0, $this->balance($item));
        $this->confirm($cog)->assertSessionHasNoErrors(); $this->confirm($cog)->assertSessionHasNoErrors();
        $this->assertSame(40.0, $this->balance($item));
        $this->confirm($other)->assertSessionHasErrors('stock');
        $this->assertSame('draft', $other->fresh()->status);
        $this->post(route('miri-cogs.cancel', $cog), ['reason' => 'Cannot cancel posted stock'])->assertStatus(409);
        $this->confirm($this->cog($item, 10, 'Received backload'))->assertSessionHasNoErrors();
        $this->assertSame(50.0, $this->balance($item));
        $this->postStock($item, [...$this->data($item, 'issue'), 'rows' => [['quantity' => 1, 'destination' => 'X', 'reference' => $other->cog_no, 'document_date' => '2026-10-27', 'note' => 'Duplicate']]])->assertSessionHasErrors('stock');
    }
    public function test_transfers_require_both_yards_and_post_both_sides_once(): void
    {
        $item = $this->item(); $this->postStock($item, $this->data($item))->assertSessionHasNoErrors();
        $cog = $this->cog($item, 20, 'Transfer');
        $this->confirm($cog)->assertSessionHasErrors('stock'); $this->assertSame(100.0, $this->balance($item));
        $this->postStock($item, $this->data($item, 'opening', 0, 'LABUAN'))->assertSessionHasNoErrors();
        $this->confirm($cog)->assertSessionHasNoErrors(); $this->confirm($cog)->assertSessionHasNoErrors();
        $this->assertSame(80.0, $this->balance($item)); $this->assertSame(20.0, $this->balance($item, 'LABUAN'));
    }
    public function test_mixed_construction_and_scaffolding_confirmation_rolls_back_together(): void
    {
        $item = $this->item();
        $construction = MiriConstructionItem::create(['branch_id' => $item->branch_id, 'category' => 'PPE', 'description' => 'Gloves', 'unit' => 'PAIR', 'current_location' => 'BTU']);
        $ledger = app(\App\Services\ConstructionStockLedger::class); $construction->refresh();
        $this->post(route('construction.stock', $construction), ['kind' => 'opening', 'quantity' => 100, 'unit' => 'PAIR', 'location' => 'BTU', 'note' => 'Verified', 'reference' => '', 'request_key' => (string) Str::uuid(), 'stock_token' => $ledger->token($construction), 'confirmed' => true])->assertSessionHasNoErrors();
        $cog = $this->cog($item, 10, 'Issue out', [['item_type' => 'Construction', 'item_id' => $construction->id, 'unit' => 'PAIR', 'quantity' => 5]]);
        $this->confirm($cog)->assertSessionHasErrors('stock');
        $this->assertEquals(100, $construction->fresh()->stock_balance); $this->assertNull($cog->fresh()->construction_stock_confirmed_at);
        $this->postStock($item, $this->data($item))->assertSessionHasNoErrors();
        $this->confirm($cog)->assertSessionHasNoErrors();
        $this->assertEquals(95, $construction->fresh()->stock_balance); $this->assertSame(90.0, $this->balance($item));
    }
    public function test_verified_units_cannot_be_reinterpreted(): void
    {
        $item = $this->item(); $this->postStock($item, $this->data($item))->assertSessionHasNoErrors();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $item->update(['unit' => 'SET']);
    }

    public function test_options_and_stock_are_scoped_and_require_edit_access(): void
    {
        $item = $this->item();
        $this->post(route('scaffolding.options.store'), ['kind' => 'contract', 'value' => 'NEW CONTRACT'])->assertSessionHasNoErrors();
        $this->get(route('major-equipment.edit', $item))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('scaffoldingOptions.contract.0', 'NEW CONTRACT'));
        $foreign = MajorEquipment::withoutGlobalScopes()->create(['branch_id' => Branch::where('code', '!=', 'MIRI')->value('id'), 'description' => 'Other branch', 'section_1' => 'SCAFFOLDING SET', 'unit' => 'PC']);
        $this->postStock($foreign, $this->data($foreign))->assertNotFound();
        $normal = $this->item(); $normal->update(['section_1' => 'Machinery']);
        $this->postStock($normal, $this->data($normal))->assertNotFound();
        $user = auth()->user(); $permissions = $user->permissions; $permissions['assets'] = 'read';
        $user->update(['permissions' => $permissions]); $this->actingAs($user->fresh());
        $this->postStock($item, $this->data($item))->assertForbidden();
        $this->post(route('scaffolding.options.store'), ['kind' => 'contract', 'value' => 'DENIED'])->assertForbidden();
        $this->assertDatabaseCount('scaffolding_stock_accounts', 0);
    }
}
