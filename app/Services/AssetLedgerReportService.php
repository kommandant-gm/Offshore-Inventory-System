<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetLedgerReportService
{
    public function rows(int $year, int $month, ?int $categoryId = null): Collection
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();

        $aggregateSubquery = DB::table('inventory_transactions')
            ->selectRaw('
                item_id,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as total_received,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as total_issued,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as interloc_transfer,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as material_return,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as physical_adjustment,
                SUM(CASE WHEN transaction_type = ? THEN quantity ELSE 0 END) as other_misc,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as total_received_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as total_issued_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as interloc_transfer_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as material_return_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as physical_adjustment_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as price_adjustment_value,
                SUM(CASE WHEN transaction_type = ? THEN total_value ELSE 0 END) as other_misc_value
            ', [
                InventoryTransactionType::Receive->value,
                InventoryTransactionType::Issue->value,
                InventoryTransactionType::InterlocTransfer->value,
                InventoryTransactionType::MaterialReturn->value,
                InventoryTransactionType::PhysicalAdjustment->value,
                InventoryTransactionType::OtherMisc->value,
                InventoryTransactionType::Receive->value,
                InventoryTransactionType::Issue->value,
                InventoryTransactionType::InterlocTransfer->value,
                InventoryTransactionType::MaterialReturn->value,
                InventoryTransactionType::PhysicalAdjustment->value,
                InventoryTransactionType::PriceAdjustment->value,
                InventoryTransactionType::OtherMisc->value,
            ])
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->groupBy('item_id');

        $latestTransactionKeySubquery = DB::table('inventory_transactions')
            ->selectRaw("
                item_id,
                MAX(CONCAT(DATE_FORMAT(transaction_date, '%Y%m%d'), LPAD(id, 10, '0'))) as latest_sort_key
            ")
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->groupBy('item_id');

        return InventoryItem::query()
            ->select([
                'inventory_items.id',
                'inventory_items.description',
                'inventory_items.opening_stock',
                'inventory_items.standard_cost',
                'inventory_items.uom',
                'inventory_items.rack_no',
                'inventory_items.remarks',
                'inventory_items.category_id',
            ])
            ->selectRaw('
                ledger_totals.total_received,
                ledger_totals.total_issued,
                ledger_totals.interloc_transfer,
                ledger_totals.material_return,
                ledger_totals.physical_adjustment,
                ledger_totals.other_misc,
                ledger_totals.total_received_value,
                ledger_totals.total_issued_value,
                ledger_totals.interloc_transfer_value,
                ledger_totals.material_return_value,
                ledger_totals.physical_adjustment_value,
                ledger_totals.price_adjustment_value,
                ledger_totals.other_misc_value,
                latest_transaction.cog_issued_out,
                latest_transaction.cog_received,
                latest_transaction.po_no,
                latest_transaction.do_no,
                latest_transaction.remarks as latest_transaction_remarks
            ')
            ->leftJoinSub($aggregateSubquery, 'ledger_totals', fn ($join) => $join->on('ledger_totals.item_id', '=', 'inventory_items.id'))
            ->leftJoinSub($latestTransactionKeySubquery, 'latest_transaction_keys', fn ($join) => $join->on('latest_transaction_keys.item_id', '=', 'inventory_items.id'))
            ->leftJoin('inventory_transactions as latest_transaction', function ($join) {
                $join->on('latest_transaction.item_id', '=', 'inventory_items.id')
                    ->whereRaw("
                        CONCAT(DATE_FORMAT(latest_transaction.transaction_date, '%Y%m%d'), LPAD(latest_transaction.id, 10, '0')) = latest_transaction_keys.latest_sort_key
                    ");
            })
            ->when($categoryId, fn ($query) => $query->where('inventory_items.category_id', $categoryId))
            ->orderBy('inventory_items.description')
            ->get()
            ->values()
            ->map(function (InventoryItem $item, int $index) {
                $openingStock = (float) $item->opening_stock;
                $unitPrice = (float) $item->standard_cost;
                $received = (float) ($item->total_received ?? 0);
                $issued = (float) ($item->total_issued ?? 0);
                $interlocTransfer = (float) ($item->interloc_transfer ?? 0);
                $materialReturn = (float) ($item->material_return ?? 0);
                $physicalAdjustment = (float) ($item->physical_adjustment ?? 0);
                $otherMisc = (float) ($item->other_misc ?? 0);
                $priceAdjustmentValue = (float) ($item->price_adjustment_value ?? 0);
                $closingStock = $openingStock + $received - $issued + $materialReturn + $physicalAdjustment + $otherMisc;

                return [
                    'no' => $index + 1,
                    'description' => $item->description,
                    'opening_stock' => $openingStock,
                    'total_received' => $received,
                    'total_issued' => $issued,
                    'interloc_transfer' => $interlocTransfer,
                    'material_return' => $materialReturn,
                    'physical_adjustment' => $physicalAdjustment,
                    'price_adjustment' => $priceAdjustmentValue,
                    'other_misc' => $otherMisc,
                    'closing_stock' => $closingStock,
                    'unit_measure' => $item->uom,
                    'unit_price' => $unitPrice,
                    'opening_stock_value' => $openingStock * $unitPrice,
                    'total_received_value' => (float) ($item->total_received_value ?? 0),
                    'total_issued_value' => (float) ($item->total_issued_value ?? 0),
                    'interloc_transfer_value' => (float) ($item->interloc_transfer_value ?? 0),
                    'material_return_value' => (float) ($item->material_return_value ?? 0),
                    'physical_adjustment_value' => (float) ($item->physical_adjustment_value ?? 0),
                    'price_adjustment_value' => $priceAdjustmentValue,
                    'other_misc_value' => (float) ($item->other_misc_value ?? 0),
                    'closing_stock_value' => ($closingStock * $unitPrice) + $priceAdjustmentValue,
                    'rack_no' => $item->rack_no,
                    'cog_issued_out' => $item->cog_issued_out,
                    'cog_received' => $item->cog_received,
                    'purchase_order_no' => $item->po_no,
                    'delivery_order_no' => $item->do_no,
                    'remarks' => $item->latest_transaction_remarks ?: $item->remarks,
                ];
            });
    }
}
