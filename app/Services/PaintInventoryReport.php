<?php

namespace App\Services;

use App\Models\MiriPaintItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaintInventoryReport
{
    public const COLUMNS = [
        'id' => 'Record', 'description' => 'Description', 'batch' => 'Batch', 'brand' => 'Brand',
        'opening' => 'Opening stock', 'received' => 'Total received', 'issued' => 'Tracked issues',
        'other_outbound' => 'Other outbound', 'returns' => 'Backloads / reversals', 'adjustments' => 'Adjustments',
        'closing' => 'Balance stock', 'unit' => 'Unit', 'location' => 'Storage location',
        'po' => 'Purchase order', 'do' => 'Delivery order', 'remarks' => 'Remarks',
    ];

    public function generate(int $branch, array $filters): array
    {
        $notes = [
            'Prices are excluded. CAN and LTR are separate quantities; do not add them together.',
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
            if ($classification->location($item->current_location) !== $filters['location']) {
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
                $rows[] = [
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
