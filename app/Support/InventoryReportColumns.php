<?php

namespace App\Support;

class InventoryReportColumns
{
    public const COLUMNS = [
        'number' => 'Item', 'description' => 'Decription', 'brand' => 'Brand',
        'opening' => 'Opening Stock', 'received' => 'Total Received', 'issued' => 'Total Issued (W/H Used)',
        'closing' => 'Balance Stock', 'unit' => 'Unit', 'unit_price' => 'Unit Price',
        'opening_value' => 'Opening Stock Value', 'received_value' => 'Total Received Value',
        'issued_value' => 'Total Issued Value (W/H Used)', 'closing_value' => 'Balance Stk Value',
        'location' => 'Location Storage', 'po' => 'Purchase Order No.', 'do' => 'Delivery Order No.', 'remarks' => 'Remarks',
    ];
}
