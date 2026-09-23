<?php

namespace App\Services;

class PpeReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(InventoryReportWorkbook::class)->create($report, $filters, [
            'template' => 'ppe-inventory.xlsx', 'location' => 'BINTULU YARD',
            'heading' => 'GENERAL STORE PPE INVENTORY STATUS', 'sheet' => 'PPE',
            'total' => 'GRAND TOTAL FOR PPE ITEM',
        ]);
    }
}
