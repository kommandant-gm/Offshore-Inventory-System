<?php

namespace App\Services;

class ConsumableReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(InventoryReportWorkbook::class)->create($report, $filters, [
            'template' => 'consumable-inventory.xlsx', 'location' => 'BINTULU YARD',
            'heading' => 'GENERAL STORE CONSUMABLE INVENTORY STATUS', 'sheet' => 'CONSUMABLE',
            'total' => 'GRAND TOTAL FOR CONSUMABLE ITEM',
        ]);
    }
}
