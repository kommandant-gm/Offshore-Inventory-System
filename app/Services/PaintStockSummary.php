<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class PaintStockSummary
{
    public function data(Builder $query): array
    {
        $stockQuery = (clone $query)->reorder()->select([])->selectRaw('COUNT(*) as records');
        foreach (['opening_cans', 'opening_litres', 'opening_total_price', 'balance_cans', 'balance_litres', 'closing_total_price'] as $column) {
            $stockQuery->selectRaw("SUM({$column}) as {$column}, COUNT({$column}) as {$column}_count");
        }
        foreach (['opening_unit_price', 'closing_unit_price'] as $column) {
            $stockQuery->selectRaw("MIN({$column}) as {$column}_min, MAX({$column}) as {$column}_max, COUNT({$column}) as {$column}_count");
        }
        $summary = (array) $stockQuery->toBase()->first();
        // Preserve imported spellings while grouping known brand aliases for display.
        $brand = "CASE WHEN UPPER(TRIM(section_2)) IN ('IP', 'IP PAINT', 'INTERNATION', 'INTERNATION PAINT', 'INTERNATIONAL', 'INTERNATIONAL PAINT') THEN 'ip'
            WHEN UPPER(TRIM(section_2)) IN ('HEMPEL', 'HEMPEL PAINT') THEN 'hempel' ELSE 'other' END";
        $groups = (clone $query)->reorder()->select([])->selectRaw("{$brand} as paint_type, COUNT(*) as records")
            ->selectRaw('SUM(opening_litres) as opening_litres, COUNT(opening_litres) as opening_litres_count, SUM(balance_litres) as balance_litres, COUNT(balance_litres) as balance_litres_count')
            ->groupByRaw($brand)->toBase()->get()->keyBy('paint_type');
        foreach (['ip', 'hempel', 'other'] as $type) {
            $summary['paint_types'][$type] = isset($groups[$type]) ? (array) $groups[$type] : [
                'records' => 0, 'opening_litres' => null, 'opening_litres_count' => 0,
                'balance_litres' => null, 'balance_litres_count' => 0,
            ];
        }
        return $summary;
    }
}
