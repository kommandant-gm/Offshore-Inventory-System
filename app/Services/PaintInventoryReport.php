<?php

namespace App\Services;

use App\Models\MiriPaintItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaintInventoryReport
{
    public const COLUMNS = \App\Support\InventoryReportColumns::COLUMNS;

    public function generate(int $branch, array $filters): array
    {
        $notes = [
            'Opening/closing values are recorded item totals, not recalculated, and appear once on the LTR row. Unit price uses the recorded closing price (or opening price if absent) and item unit.',
            'Prices are included where recorded for the current stock period. Historical prices are not snapshotted. CAN and LTR are separate quantities; mixed-unit grand totals are left blank.',
            'Total received is blank: purchase receipts are not separately dated in the stock ledger. Imported receipt quantities are not monthly totals.',
            'Movement columns show posted ledger activity in the selected stock month, including posted draft issue notes. Zero means no tracked movement, not proof of no historical activity.',
            'Backloads and cancellation reversals are shown in the month posted. Other outbound includes transfers and supplier returns. Adjustments are not treated as receipts.',
            'Descriptions, brands, locations and PO/DO references use current item details. Historical locations and references are not snapshotted.',
        ];
        foreach (['miri_paint_items', 'miri_paint_stock_months', 'miri_paint_stock_movements'] as $table) {
            if (! Schema::hasTable($table)) {
                return ['rows' => [], 'notes' => $notes, 'unavailable' => 'Paint reporting requires the existing paint register and stock-history database migrations.'];
            }
        }
        $period = $filters['month'].'-01';
        $classification = app(PaintQuantitySummary::class);
        // Read only: generating a report must never trigger stock rollover.
        $items = MiriPaintItem::withoutGlobalScopes()->where('branch_id', $branch)->orderBy('description')->orderBy('id')->get();
        $months = DB::table('miri_paint_stock_months')->where('branch_id', $branch)->where('period', $period)->get()->keyBy('paint_item_id');
        $movements = DB::table('miri_paint_stock_movements as m')
            ->leftJoin('miri_cog_items as l', 'l.id', '=', 'm.cog_item_id')
            ->leftJoin('miri_cogs as c', 'c.id', '=', 'l.miri_cog_id')
            ->where('m.branch_id', $branch)->where('m.period', $period)
            ->get(['m.*', 'c.movement_type'])->groupBy('paint_item_id');
        $rows = [];
        foreach ($items as $item) {
            $brand = $classification->brand($item->section_2);
            if (! in_array($classification->location($item->current_location), $filters['location'] === 'all' ? ['BTU', 'LBN'] : [$filters['location']], true)) {
                continue;
            }
            if ($filters['brand'] !== 'all' && $brand !== $filters['brand']) {
                continue;
            }
            $snapshot = $months->get($item->id);
            $current = $item->stock_period === $period;
            $activity = $movements->get($item->id, collect());
            // Do not include items first recorded after the requested period unless history exists.
            if (! $snapshot && ! $current && $activity->isEmpty() && $item->created_at?->format('Y-m') > $filters['month']) {
                continue;
            }
            foreach (['LTR' => 'litres', 'CAN' => 'cans'] as $unit => $suffix) {
                $opening = $snapshot ? $snapshot->{'opening_'.$suffix} : ($current ? $item->{'opening_'.$suffix} : null);
                $closing = $snapshot ? $snapshot->{'closing_'.$suffix} : ($current ? $item->{'balance_'.$suffix} : null);
                $entries = $activity->where('unit', $unit);
                if ($unit === 'CAN' && $opening === null && $closing === null && $entries->isEmpty()) {
                    continue;
                }
                $known = $snapshot || $current || $entries->isNotEmpty();
                $totals = ['issued' => 0, 'other_outbound' => 0, 'returns' => 0, 'adjustments' => 0];
                foreach ($entries as $entry) {
                    $key = match ($entry->kind) {
                        'outbound' => $entry->movement_type === 'Issue out' ? 'issued' : 'other_outbound',
                        'backload', 'cancellation' => 'returns',
                        default => 'adjustments',
                    };
                    if ($entry->quantity === null) {
                        $totals[$key] = null;

                        continue;
                    }
                    if ($totals[$key] !== null) {
                        $totals[$key] += (int) round((float) $entry->quantity * 1000) * ($entry->kind === 'outbound' ? -1 : 1);
                    }
                }
                $remarks = [];
                if (! $snapshot && ! $current) {
                    $remarks[] = 'Monthly balance history unavailable';
                }
                if ($opening === null) {
                    $remarks[] = 'Opening balance missing';
                }
                if ($closing === null) {
                    $remarks[] = 'Closing balance missing';
                }
                if ($remarks !== []) {
                    $notes[] = 'Item '.(count($rows) + 1).' / '.$unit.': '.implode('; ', $remarks).'.';
                }
                $remarks = [];
                if ($item->batch_no) {
                    $remarks[] = 'Batch: '.$item->batch_no;
                }
                foreach (['other_outbound' => 'Other outbound', 'returns' => 'Backloads / reversals', 'adjustments' => 'Adjustments'] as $key => $label) {
                    if ($totals[$key] === null) {
                        $remarks[] = $label.': unknown';
                    } elseif ($totals[$key] !== 0) {
                        $remarks[] = $label.': '.($totals[$key] / 1000).' '.$unit;
                    }
                }
                $money = fn ($value) => $value === null ? null : (float) $value;
                $priceUnit = match (strtoupper(trim($item->unit ?? ''))) {
                    'LTR', 'L', 'LITRE', 'LITRES', 'LITER', 'LITERS' => 'LTR',
                    'CAN', 'CANS' => 'CAN', default => null,
                };
                // Saved monetary totals describe the item, not each parallel unit row.
                $showMoney = $current && $unit === 'LTR';
                $unitPrice = $current && $priceUnit === $unit ? $money($item->closing_unit_price ?? $item->opening_unit_price) : null;
                $rows[] = [
                    'number' => count($rows) + 1,
                    'unit_price' => $unitPrice, 'opening_value' => $showMoney ? $money($item->opening_total_price) : null, 'received_value' => null, 'issued_value' => null, 'closing_value' => $showMoney ? $money($item->closing_total_price) : null,
                    'id' => $item->id, 'description' => $item->description, 'batch' => $item->batch_no, 'brand' => $brand,
                    'opening' => $opening === null ? null : (float) $opening, 'received' => null,
                    ...array_map(fn ($value) => ! $known || $value === null ? null : $value / 1000, $totals),
                    'closing' => $closing === null ? null : (float) $closing, 'unit' => $unit,
                    'location' => implode(' / ', array_filter([$item->current_location, $item->storage_rack])),
                    'po' => $item->po_reference, 'do' => $item->do_reference, 'remarks' => implode('; ', $remarks),
                ];
            }
        }

        return ['rows' => $rows, 'notes' => $notes, 'unavailable' => null];
    }
}
