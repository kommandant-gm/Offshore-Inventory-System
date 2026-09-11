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
        return (array) $stockQuery->toBase()->first();
    }
}
