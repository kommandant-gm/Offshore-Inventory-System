<?php

namespace Tests\Feature;

use App\Domain\Inventory\InventoryBalance;
use App\Enums\CategoryType;
use App\Enums\InventoryTransactionType;
use App\Enums\LocationType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Cog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Stocktake;
use App\Models\User;
use App\Services\InventoryLocationBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_updates_location_balances_without_reducing_global_stock(): void
    {
        [$user, $category, $source, $destination, $item] = $this->seedInventoryFixture(10);

        $response = $this->actingAs($user)->post(route('asset-movements.store'), [
            'transaction_date' => '2026-07-07',
            'item_id' => $item->id,
            'location_id' => $source->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'transaction_type' => InventoryTransactionType::InterlocTransfer->value,
            'quantity' => 4,
            'unit_cost' => 100,
        ]);

        $response->assertRedirect(route('asset-movements.index'));

        $item->refresh()->load('locationBalances');

        $this->assertSame(10.0, InventoryBalance::currentQuantity($item));
        $this->assertSame(6.0, (float) $item->locationBalances->firstWhere('location_id', $source->id)?->quantity);
        $this->assertSame(4.0, (float) $item->locationBalances->firstWhere('location_id', $destination->id)?->quantity);
    }

    public function test_ledger_closing_stock_is_not_reduced_by_transfer_movements(): void
    {
        [$user, $category, $source, $destination, $item] = $this->seedInventoryFixture(10);

        InventoryTransaction::query()->create([
            'transaction_date' => '2026-07-07',
            'item_id' => $item->id,
            'location_id' => $source->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'transaction_type' => InventoryTransactionType::InterlocTransfer,
            'quantity' => 4,
            'unit_cost' => 100,
            'total_value' => 400,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('asset-ledger.index', [
            'year' => 2026,
            'month' => 7,
            'category' => $category->id,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Assets/Ledger/Index')
            ->where('rows.0.interloc_transfer', 4)
            ->where('rows.0.closing_stock', 10)
        );
    }

    public function test_stocktake_posts_adjustment_and_audit_log(): void
    {
        [$user, , $source, , $item] = $this->seedInventoryFixture(8);

        $response = $this->actingAs($user)->post(route('stocktakes.store'), [
            'stocktake_date' => '2026-07-07',
            'location_id' => $source->id,
            'remarks' => 'Cycle count',
            'items' => [
                [
                    'inventory_item_id' => $item->id,
                    'counted_quantity' => 6,
                    'remarks' => 'Two units missing',
                ],
            ],
        ]);

        $stocktake = Stocktake::query()->firstOrFail();
        $response->assertRedirect(route('stocktakes.show', $stocktake));

        $item->refresh()->load('locationBalances');
        $adjustment = InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::PhysicalAdjustment)
            ->firstOrFail();

        $this->assertSame(-2.0, (float) $adjustment->quantity);
        $this->assertSame(6.0, (float) $item->locationBalances->firstWhere('location_id', $source->id)?->quantity);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'movements',
            'event' => 'stocktake_completed',
        ]);
    }

    public function test_issue_with_generated_cog_creates_linked_records_and_audit_log(): void
    {
        Mail::fake();

        [$user, , $source, $destination, $item] = $this->seedInventoryFixture(5);

        $response = $this->actingAs($user)->post(route('asset-movements.store'), [
            'transaction_date' => '2026-07-07',
            'item_id' => $item->id,
            'location_id' => $source->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'transaction_type' => InventoryTransactionType::Issue->value,
            'quantity' => 2,
            'unit_cost' => 50,
            'generate_cog' => true,
            'cog' => [
                'document_date' => '2026-07-07',
                'consignee' => 'Rig Team',
                'destination' => 'Rig Alpha',
                'receiver_name' => 'Receiver',
                'receiver_email' => 'receiver@example.com',
            ],
        ]);

        $cog = Cog::query()->firstOrFail();
        $transaction = InventoryTransaction::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('cogs.show', $cog));
        $this->assertSame($cog->id, $transaction->cog_id);
        $this->assertSame($cog->cog_no, $transaction->cog_issued_out);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'cogs',
            'event' => 'created',
        ]);
    }

    /**
     * @return array{User, Category, Location, Location, InventoryItem}
     */
    private function seedInventoryFixture(float $openingStock): array
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'code' => 'CAT-001',
            'name' => 'Deck Consumables',
            'type' => CategoryType::Asset,
            'active' => true,
        ]);

        $source = Location::query()->create([
            'code' => 'LOC-A',
            'name' => 'Main Store',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        $destination = Location::query()->create([
            'code' => 'LOC-B',
            'name' => 'Offshore Base',
            'type' => LocationType::Offshore,
            'active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'item_code' => 'ITEM-001',
            'description' => 'Testing item',
            'category_id' => $category->id,
            'uom' => 'EA',
            'default_location_id' => $source->id,
            'opening_stock' => $openingStock,
            'standard_cost' => 100,
            'minimum_stock' => 1,
            'active' => true,
        ]);

        app(InventoryLocationBalanceService::class)->syncItem($item->fresh(['transactions', 'locationBalances']));

        return [$user, $category, $source, $destination, $item];
    }
}
