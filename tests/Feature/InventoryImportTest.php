<?php

namespace Tests\Feature;

use App\Domain\Inventory\InventoryBalance;
use App\Enums\InventoryTransactionType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_creates_items_and_supported_movements(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'report_row_no,proposed_item_code,category_name,location_name,description,uom,rack_no,opening_stock,total_received,total_issued,interloc_transfer,material_return,physical_adjustment,price_adjustment,other_misc,closing_stock,unit_price,purchase_order_no,delivery_order_no,remarks,active',
            '1,CON-LBW-0001,Consumables,Labuan Warehouse - Yard 1 (General Store),Test Imported Item,PCS,GS-1-A,10,5,3,2,1,-1,4,0,12,8.50,PO-1,DO-1,Initial workbook import,1',
        ]);

        $response = $this->actingAs($user)->post(route('assets.import.store'), [
            'movement_date' => '2026-04-30',
            'file' => UploadedFile::fake()->createWithContent('inventory-import.csv', $csv),
        ]);

        $response->assertRedirect(route('assets.index'));

        $category = Category::query()->firstOrFail();
        $location = Location::query()->firstOrFail();
        $item = InventoryItem::query()->firstOrFail()->fresh(['transactions', 'locationBalances']);

        $this->assertSame('Consumables', $category->name);
        $this->assertSame('Labuan Warehouse - Yard 1 (General Store)', $location->name);
        $this->assertSame('CON-LBW-0001', $item->item_code);
        $this->assertSame(10.0, (float) $item->opening_stock);
        $this->assertSame(12.0, InventoryBalance::currentQuantity($item));
        $this->assertStringContainsString('Inter-location transfer total from import sheet was not posted automatically.', (string) $item->remarks);
        $this->assertStringContainsString('Price adjustment total from import sheet was not posted automatically.', (string) $item->remarks);

        $this->assertDatabaseCount('inventory_transactions', 4);
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'transaction_type' => InventoryTransactionType::Receive->value,
            'quantity' => 5.00,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'transaction_type' => InventoryTransactionType::Issue->value,
            'quantity' => 3.00,
            'source_location_id' => $location->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'transaction_type' => InventoryTransactionType::MaterialReturn->value,
            'quantity' => 1.00,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'transaction_type' => InventoryTransactionType::PhysicalAdjustment->value,
            'quantity' => -1.00,
        ]);

        $this->assertSame(0, InventoryTransaction::query()->where('transaction_type', InventoryTransactionType::InterlocTransfer->value)->count());
        $this->assertSame(0, InventoryTransaction::query()->where('transaction_type', InventoryTransactionType::PriceAdjustment->value)->count());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'assets',
            'event' => 'imported',
        ]);
    }

    public function test_csv_import_skips_existing_item_codes_on_rerun(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'report_row_no,proposed_item_code,category_name,location_name,description,uom,rack_no,opening_stock,total_received,total_issued,interloc_transfer,material_return,physical_adjustment,price_adjustment,other_misc,closing_stock,unit_price,purchase_order_no,delivery_order_no,remarks,active',
            '1,CON-LBW-0001,Consumables,Labuan Warehouse - Yard 1 (General Store),Test Imported Item,PCS,GS-1-A,10,5,3,0,0,0,0,0,12,8.50,PO-1,DO-1,Initial workbook import,1',
        ]);

        $file = UploadedFile::fake()->createWithContent('inventory-import.csv', $csv);

        $this->actingAs($user)->post(route('assets.import.store'), [
            'movement_date' => '2026-04-30',
            'file' => $file,
        ]);

        $secondResponse = $this->actingAs($user)->post(route('assets.import.store'), [
            'movement_date' => '2026-04-30',
            'file' => UploadedFile::fake()->createWithContent('inventory-import.csv', $csv),
        ]);

        $secondResponse->assertRedirect(route('assets.index'));
        $secondResponse->assertSessionHas('success', 'Inventory import complete. 0 items created, 1 items skipped, 0 movements posted.');

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }
}
