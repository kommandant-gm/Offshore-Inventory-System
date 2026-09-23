<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class PaintStockSummary
{
    public function closingByLocation(Builder $query): array
    {
        $classification = app(PaintQuantitySummary::class);
        $locations = [];
        foreach (['BTU', 'LBN'] as $location) {
            $locations[$location] = ['label' => $location, 'paints' => []];
            foreach (['Hempel Paint', 'IP Paint'] as $paint) {
                $locations[$location]['paints'][$paint] = ['label' => $paint, 'litres' => null, 'records' => 0, 'recorded' => 0];
            }
        }
        $excluded = 0;
        $groups = (clone $query)->reorder()->select('section_2', 'current_location')
            ->selectRaw('COUNT(*) as records, COUNT(balance_litres) as recorded, SUM(balance_litres) as litres')
            ->groupBy('section_2', 'current_location')->toBase()->get();
        foreach ($groups as $group) {
            $location = $classification->location($group->current_location);
            $paint = $classification->brand($group->section_2);
            if (! isset($locations[$location]['paints'][$paint])) { $excluded += (int) $group->records; continue; }
            $row = &$locations[$location]['paints'][$paint];
            $row['records'] += (int) $group->records;
            $row['recorded'] += (int) $group->recorded;
            if ($group->litres !== null) $row['litres'] = round(($row['litres'] ?? 0) + (float) $group->litres, 3);
            unset($row);
        }
        foreach ($locations as &$location) $location['paints'] = array_values($location['paints']);
        unset($location);
        return ['locations' => array_values($locations), 'excluded_records' => $excluded];
    }

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
