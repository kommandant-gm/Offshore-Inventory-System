<?php

namespace App\Support;

class EquipmentSheet
{
    public const FIELDS = [
        'company' => 'Company', 'tag_no' => 'Tag No.', 'description' => 'Description',
        'category' => 'Category', 'section_1' => 'Section', 'section_2' => 'Subcategory',
        'model_brand' => 'Model / Brand', 'serial_no' => 'Serial No.',
        'size_model' => 'Dimensions / Model', 'size_ton' => 'Tonnage', 'size_length' => 'Length', 'quantity' => 'Quantity',
        'unit' => 'Unit', 'current_location' => 'Current location', 'issue_out_location' => 'Issue-out location', 'status' => 'Status',
        'issue_out_cog_no' => 'Issue-out COG No.', 'issue_out_cog_date' => 'Issue-out COG Date',
        'received_backload_cog_no' => 'Backload COG No.', 'received_backload_cog_date' => 'Backload COG Date',
        'mr_request' => 'MR Request', 'purchase_order' => 'Purchase Order', 'delivery_order' => 'Delivery Order',
        'supplier' => 'Supplier', 'unfit_report' => 'Unfit Report', 'write_off_reference' => 'Write-off Reference', 'remarks' => 'Remarks',
    ];

    public static function columns(string $type): array
    {
        $exclude = $type === 'cargo' ? ['model_brand', 'serial_no'] : ['size_model', 'size_ton', 'size_length', 'quantity'];
        return collect(self::FIELDS)->except($exclude)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label,
            'type' => str_ends_with($key, '_date') ? 'date' : ($key === 'quantity' ? 'number' : 'text'),
        ])->values()->all();
    }

    public static function value($item, string $key): string
    {
        $value = $item->$key;
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : \Illuminate\Support\Str::trim((string) ($value ?? ''));
    }
}
