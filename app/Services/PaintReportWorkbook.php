<?php

namespace App\Services;

class PaintReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(InventoryReportWorkbook::class)->create($report, $filters, [
            'template' => 'paint-inventory.xlsx', 'location' => 'BINTULU YARD',
            'heading' => 'GENERAL STORE PAINT INVENTORY STATUS', 'sheet' => 'PAINT',
            'total' => 'GRAND TOTAL FOR PAINT ITEM', 'money_unit' => 'LTR',
        ]);
    }
}
