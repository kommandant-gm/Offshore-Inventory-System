<?php

namespace App\Services;

class GasReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(LabuanPaintReportWorkbook::class)->create($report, $filters, [
            'title' => 'GAS INVENTORY REPORT', 'sheet' => 'GAS', 'columns' => GasInventoryReport::COLUMNS,
            'headers' => ['C2' => 'LOCATIONS: ALL — MIRI INVENTORY', 'F6' => 'PO / SR No.', 'H6' => 'DO / On-hire Date',
                'I6' => 'Serial / Tag No.', 'J6' => 'Supplier', 'J7' => null, 'J8' => null,
                'K6' => 'Register Unit', 'L6' => 'Current Status', 'M6' => 'Current Location',
                'N6' => '1st Load Out Status', 'P7' => 'Qty', 'R7' => 'Unit',
                'S6' => '1st Back Load Status', 'U7' => 'Qty', 'W7' => 'Unit',
                'X6' => '1st Return To Supplier', 'Z7' => 'Qty / Unit'],
        ]);
    }
}
