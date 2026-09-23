<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaintQuantitySummary
{
    private function location(?string $value): ?string
    {
        $value = strtoupper(trim($value ?? ''));
        foreach (['SKA' => 'SKA', 'SBA' => 'SBA', 'BTU|BINTULU' => 'BTU', 'LBN|LABUAN' => 'LBN'] as $pattern => $label) {
            if (preg_match('/\b('.$pattern.')\b/', $value)) return $label;
        }
        return null;
    }

    private function brand(?string $value): string
    {
        return match (strtoupper(trim($value ?? ''))) {
            'IP', 'IP PAINT', 'INTERNATION', 'INTERNATION PAINT', 'INTERNATIONAL', 'INTERNATIONAL PAINT' => 'IP Paint',
            'HEMPEL', 'HEMPEL PAINT' => 'Hempel Paint',
            default => 'Other / Not recorded',
        };
    }

    public function data(Builder $query): array
    {
        $period = app(PaintStockLedger::class)->period();
        // Aggregate before joining so multiple issues cannot multiply stock quantities.
        $issues = DB::table('miri_paint_stock_movements as movement')
            ->join('miri_cog_items as line', 'line.id', '=', 'movement.cog_item_id')
            ->join('miri_cogs as cog', 'cog.id', '=', 'line.miri_cog_id')
            ->where('movement.kind', 'outbound')->where('movement.unit', 'LTR')
            ->where('movement.period', $period)->where('cog.movement_type', 'Issue out')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('miri_paint_stock_movements as reversal')
                ->whereColumn('reversal.cog_item_id', 'movement.cog_item_id')->where('reversal.kind', 'cancellation'))
            ->select('movement.paint_item_id')->selectRaw('-SUM(movement.quantity) as issued')
            ->groupBy('movement.paint_item_id');
        $items = (clone $query)->reorder()->leftJoinSub($issues, 'issues', fn ($join) => $join->on('issues.paint_item_id', '=', 'miri_paint_items.id'))
            ->get(['miri_paint_items.section_2', 'miri_paint_items.current_location', 'miri_paint_items.balance_litres', 'issues.issued']);
        $empty = fn ($label) => ['label' => $label, 'stock' => null, 'recorded' => 0, 'records' => 0, 'issued' => 0.0];
        $locations = [];
        foreach (['BTU', 'LBN', 'SKA', 'SBA'] as $location) $locations[$location] = $empty($location);
        $types = [];
        foreach (['IP Paint', 'Hempel Paint', 'Other / Not recorded'] as $brand) {
            $types[$brand] = ['label' => $brand, 'locations' => ['BTU' => $empty('BTU'), 'LBN' => $empty('LBN')]];
        }
        $excluded = 0;
        $add = function (array &$row, $item) {
            $row['records']++;
            if ($item->balance_litres !== null) {
                $row['stock'] = round(($row['stock'] ?? 0) + (float) $item->balance_litres, 3);
                $row['recorded']++;
            }
            $row['issued'] = round($row['issued'] + (float) ($item->issued ?? 0), 3);
        };
        foreach ($items as $item) {
            $location = $this->location($item->current_location);
            if ($location === null) { $excluded++; continue; }
            $add($locations[$location], $item);
            if (in_array($location, ['BTU', 'LBN'])) $add($types[$this->brand($item->section_2)]['locations'][$location], $item);
        }
        foreach ($types as &$type) $type['locations'] = array_values($type['locations']);
        unset($type);
        return ['period' => $period, 'types' => array_values($types), 'locations' => array_values($locations), 'excluded_records' => $excluded];
    }
}
