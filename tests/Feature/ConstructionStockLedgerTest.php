<?php
namespace Tests\Feature;

use App\Models\{Branch, MiriCog, MiriConstructionItem, User};
use App\Services\ConstructionStockLedger;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionStockLedgerTest extends TestCase
{
    use RefreshDatabase;
    private function item(array $attributes = []): MiriConstructionItem
    {
        if (! auth()->check()) {
            $this->withoutVite();
            $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
            $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
            $this->actingAs($user);
        }
        return MiriConstructionItem::create(['branch_id' => Branch::where('code', 'MIRI')->value('id'),
            'category' => 'BLAST GRIT', 'section_1' => 'GARNET', 'description' => 'GARNET GMA', 'company' => 'DESB',
            'current_location' => 'BTU', 'unit' => 'TON', 'stock_in_qty' => 200, ...$attributes]);
    }
    private function movement(MiriConstructionItem $item, string $kind, string $quantity): array
    {
        return ['kind' => $kind, 'quantity' => $quantity, 'unit' => 'TON', 'location' => $item->current_location,
            'note' => 'Verified physical stock and document', 'reference' => 'DO-1', 'request_key' => (string) Str::uuid(),
            'stock_token' => app(ConstructionStockLedger::class)->token($item->fresh()), 'confirmed' => true];
    }
    private function opening(MiriConstructionItem $item, string $quantity = '200'): void
    {
        $this->post(route('construction.stock', $item), $this->movement($item, 'opening', $quantity))->assertSessionHasNoErrors();
    }
    private function cog(MiriConstructionItem $item, string $type, string $quantity, array $extra = []): MiriCog
    {
        $this->post(route('miri-cogs.store'), ['movement_type' => $type, 'document_date' => '2026-09-24',
            'issued_by_name' => 'Storekeeper', 'from_location' => 'BTU', 'to_location' => 'LBN',
            'items' => [['item_type' => 'Construction', 'item_id' => $item->id, 'unit' => 'TON', 'quantity' => $quantity, ...$extra]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        return MiriCog::latest('id')->firstOrFail();
    }
    private function confirm(MiriCog $cog) { return $this->post(route('miri-cogs.confirm-stock', $cog), ['confirmed' => true]); }

    public function test_baseline_receipts_writeoffs_and_corrections_post_once_and_keep_history(): void
    {
        $item = $this->item();
        $this->assertNull($item->stock_balance); // Historical 200 receipt is not automatically replayed.
        $data = $this->movement($item, 'opening', '150');
        $this->post(route('construction.stock', $item), $data)->assertSessionHasNoErrors();
        $this->post(route('construction.stock', $item), $data)->assertSessionHasNoErrors();
        $this->assertSame('150.000', $item->fresh()->stock_balance);
        $this->assertDatabaseCount('miri_construction_stock_movements', 1);
        $stale = $this->movement($item, 'receipt', '5');
        $receipt = $this->movement($item, 'receipt', '20');
        $this->post(route('construction.stock', $item), $receipt)->assertSessionHasNoErrors();
        $this->post(route('construction.stock', $item), $receipt)->assertSessionHasNoErrors();
        $this->post(route('construction.stock', $item), $stale)->assertSessionHasErrors('stock');
        $this->post(route('construction.stock', $item), $this->movement($item, 'writeoff', '2.5'))->assertSessionHasNoErrors();
        $this->assertSame('167.500', $item->fresh()->stock_balance);
        $this->post(route('construction.stock', $item), $this->movement($item, 'correction', '165'))->assertSessionHasNoErrors();
        $this->assertSame('165.000', $item->fresh()->stock_balance);
        $this->assertSame('200.000', $item->fresh()->stock_in_qty);
        $this->post(route('construction.stock', $item), $this->movement($item, 'writeoff', '166'))->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('miri_construction_stock_movements', 4);
        $this->get(route('construction.show', $item))->assertOk();
    }

    public function test_cog_drafts_leave_balance_unchanged_and_confirmation_posts_once(): void
    {
        $item = $this->item(); $this->opening($item);
        $issue = $this->cog($item, 'Issue out', '30');
        $this->assertSame('200.000', $item->fresh()->stock_balance);
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->assertSame('170.000', $item->fresh()->stock_balance);
        $this->assertSame('confirmed', $issue->fresh()->status);
        $this->post(route('miri-cogs.cancel', $issue), ['reason' => 'Cannot cancel posted stock'])->assertStatus(409);
        $backload = $this->cog($item, 'Received backload', '10');
        $this->assertSame('170.000', $item->fresh()->stock_balance);
        $this->confirm($backload)->assertSessionHasNoErrors();
        $this->assertSame('180.000', $item->fresh()->stock_balance);
        $this->confirm($this->cog($item, 'Received backload', '21'))->assertSessionHasErrors('stock');
        $this->confirm($this->cog($item, 'Return to supplier', '5'))->assertSessionHasNoErrors();
        $this->assertSame('175.000', $item->fresh()->stock_balance);
        $discarded = $this->cog($item, 'Received backload', '1');
        $this->post(route('miri-cogs.cancel', $discarded), ['reason' => 'Return not received'])->assertSessionHasNoErrors();
        $this->confirm($discarded)->assertSessionHasErrors('stock');
        $this->assertSame('175.000', $item->fresh()->stock_balance);
        $this->get(route('major-equipment.dashboard', ['view' => 'construction']))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->where('constructionDashboard.stockGroups.0.stock', fn ($value) => (float) $value === 175.0));
    }

    public function test_transfer_debits_and_credits_matching_locations_atomically(): void
    {
        $source = $this->item(); $target = $this->item(['current_location' => 'LBN']);
        $this->opening($source); $this->opening($target, '0');
        $transfer = $this->cog($source, 'Transfer', '50', ['construction_destination_id' => $target->id]);
        $this->confirm($transfer)->assertSessionHasNoErrors();
        $this->confirm($transfer)->assertSessionHasNoErrors();
        $this->assertSame('150.000', $source->fresh()->stock_balance);
        $this->assertSame('50.000', $target->fresh()->stock_balance);
        $this->assertDatabaseCount('miri_construction_stock_movements', 4);
        $target->update(['company' => 'FTSB']);
        $invalid = $this->cog($source, 'Transfer', '10', ['construction_destination_id' => $target->id]);
        $this->confirm($invalid)->assertSessionHasErrors('stock');
        $this->assertSame('150.000', $source->fresh()->stock_balance);
        $this->assertSame('50.000', $target->fresh()->stock_balance);
    }

    public function test_stock_checks_units_baselines_and_atomic_multi_line_overdraw(): void
    {
        $item = $this->item();
        $draft = $this->cog($item, 'Issue out', '150');
        $this->confirm($draft)->assertSessionHasErrors('stock');
        $this->opening($item);
        $draft->items()->create([...$draft->items->first()->only(['branch_id', 'item_type', 'item_id', 'unit', 'current_location']), 'quantity' => 100]);
        $this->confirm($draft)->assertSessionHasErrors('stock');
        $this->assertSame('200.000', $item->fresh()->stock_balance);
        $this->assertNull($draft->fresh()->construction_stock_confirmed_at);
        $this->assertDatabaseCount('miri_construction_stock_movements', 1);
        $wrongUnit = $this->cog($item, 'Issue out', '2', ['unit' => 'KG']);
        $this->confirm($wrongUnit)->assertSessionHasErrors('stock');
        $historical = $this->cog($item, 'Issue out', '2');
        $historical->update(['construction_stock_workflow' => false]);
        $this->confirm($historical)->assertSessionHasErrors('stock');
        $this->patch(route('construction.update', $item), ['category' => 'BLAST GRIT', 'stock_balance' => 999])->assertSessionHasErrors('stock_balance');
        $this->patch(route('construction.update', $item), ['category' => 'BLAST GRIT', 'description' => 'Updated metadata'])->assertSessionHasNoErrors();
        $this->assertSame('200.000', $item->fresh()->stock_balance);
    }

    public function test_permissions_branch_isolation_and_confirmation_are_enforced(): void
    {
        $item = $this->item();
        $foreign = $this->item(['branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $data = $this->movement($item, 'opening', '200');
        $this->post(route('construction.stock', $foreign), $data)->assertNotFound();
        $data['confirmed'] = false;
        $this->post(route('construction.stock', $item), $data)->assertSessionHasErrors('confirmed');
        $cog = $this->cog($item, 'Issue out', '2');
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->post(route('construction.stock', $item), $data)->assertForbidden();
        $this->confirm($cog)->assertForbidden();
        $this->assertDatabaseCount('miri_construction_stock_movements', 0);
    }
}
