<?php

namespace Tests\Feature\Assistant;

use App\Enums\CategoryType;
use App\Enums\LocationType;
use App\Enums\InventoryTransactionType;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_anomalies_can_be_filtered_by_location_name(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
        ]);

        $labuan = Location::query()->create([
            'code' => 'LAB',
            'name' => 'Labuan Inventory',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        $miri = Location::query()->create([
            'code' => 'MRI',
            'name' => 'Miri Inventory',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'CON',
            'name' => 'Consumables',
            'type' => CategoryType::Inventory,
            'active' => true,
        ]);

        InventoryItem::query()->create([
            'item_code' => 'LAB-ITEM-001',
            'description' => 'Labuan hose',
            'category_id' => $category->id,
            'uom' => 'EA',
            'default_location_id' => $labuan->id,
            'opening_stock' => 0,
            'minimum_stock' => 5,
            'active' => true,
        ]);

        InventoryItem::query()->create([
            'item_code' => 'MRI-ITEM-001',
            'description' => 'Miri rope',
            'category_id' => $category->id,
            'uom' => 'EA',
            'default_location_id' => $miri->id,
            'opening_stock' => 0,
            'minimum_stock' => 5,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('assistant.query'), [
            'message' => 'Show anomalies for Labuan Inventory',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'anomalies')
            ->assertJsonPath('items.0.item_code', 'LAB-ITEM-001');

        $this->assertStringContainsString('Labuan Inventory', $response->json('answer'));
    }

    public function test_location_count_queries_return_live_inventory_totals(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
        ]);

        $creator = User::factory()->create();

        $location = Location::query()->create([
            'code' => 'LAB',
            'name' => 'Labuan Inventory',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'CON',
            'name' => 'Consumables',
            'type' => CategoryType::Inventory,
            'active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'item_code' => 'LAB-COUNT-001',
            'description' => 'Counted item',
            'category_id' => $category->id,
            'uom' => 'EA',
            'default_location_id' => $location->id,
            'opening_stock' => 3,
            'minimum_stock' => 1,
            'active' => true,
        ]);

        InventoryTransaction::query()->create([
            'transaction_date' => now()->toDateString(),
            'item_id' => $item->id,
            'location_id' => $location->id,
            'transaction_type' => InventoryTransactionType::Receive,
            'quantity' => 2,
            'unit_cost' => 0,
            'total_value' => 0,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('assistant.query'), [
            'message' => 'How many items are in Labuan Inventory?',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'count_items');

        $this->assertStringContainsString('There are 1 items in Labuan Inventory', $response->json('answer'));
        $this->assertStringContainsString('5 units', $response->json('answer'));
    }

    public function test_movement_queries_use_the_latest_recorded_transaction(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
        ]);

        $creator = User::factory()->create();

        $source = Location::query()->create([
            'code' => 'LAB',
            'name' => 'Labuan Inventory',
            'type' => LocationType::Yard,
            'active' => true,
        ]);

        $destination = Location::query()->create([
            'code' => 'MRI',
            'name' => 'Miri Inventory',
            'type' => LocationType::Offshore,
            'active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'CON',
            'name' => 'Consumables',
            'type' => CategoryType::Inventory,
            'active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'item_code' => 'MOVE-001',
            'description' => 'Moved item',
            'category_id' => $category->id,
            'uom' => 'EA',
            'default_location_id' => $source->id,
            'opening_stock' => 10,
            'minimum_stock' => 1,
            'active' => true,
        ]);

        InventoryTransaction::query()->create([
            'transaction_date' => now()->subDay()->toDateString(),
            'item_id' => $item->id,
            'location_id' => $source->id,
            'transaction_type' => InventoryTransactionType::Receive,
            'quantity' => 2,
            'unit_cost' => 0,
            'total_value' => 0,
            'created_by' => $creator->id,
        ]);

        InventoryTransaction::query()->create([
            'transaction_date' => now()->toDateString(),
            'item_id' => $item->id,
            'location_id' => $source->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'transaction_type' => InventoryTransactionType::InterlocTransfer,
            'quantity' => 3,
            'unit_cost' => 0,
            'total_value' => 0,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('assistant.query'), [
            'message' => 'Last movement for MOVE-001',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'movement');

        $this->assertStringContainsString('Interloc Transfer', $response->json('answer'));
        $this->assertStringContainsString('Labuan Inventory', $response->json('answer'));
        $this->assertStringContainsString('Miri Inventory', $response->json('answer'));
    }

    public function test_assistant_query_requires_assistant_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'permissions' => [
                'assistant' => 'none',
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('assistant.query'), [
                'message' => 'Where is CON-Y1-0001?',
            ])
            ->assertForbidden();
    }
}
