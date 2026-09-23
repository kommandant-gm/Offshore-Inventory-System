<?php

namespace App\Services;

class CidbReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(InventoryReportWorkbook::class)->create($report, $filters, [
            'template' => 'cidb-training-inventory.xlsx', 'location' => 'BINTULU NEW YARD',
            'heading' => 'CIDB TRAINING ITEM STOCK INVENTORY STATUS', 'sheet' => 'CIDB',
            'total' => 'GRAND TOTAL FOR CIDB TRAINING ITEM',
        ]);
    }
}
