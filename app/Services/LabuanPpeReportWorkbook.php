<?php

namespace App\Services;

class LabuanPpeReportWorkbook
{
    public function create(array $report, array $filters): string
    {
        return app(LabuanConsumableReportWorkbook::class)->create($report, $filters, [
            'template' => 'labuan-ppe-inventory.xlsx', 'location' => 'LABUAN WAREHOUSE PPE STORE',
            'sheet' => 'PPE STORE', 'total' => false, 'preserve_styles' => true,
        ]);
    }
}
