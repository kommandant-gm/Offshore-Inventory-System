<?php
namespace App\Support;

class PaintFields
{
    public const FIELDS = [
        ['key' => 'category', 'label' => 'CATEGORY', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'section_1', 'label' => 'SECTION 1', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'section_2', 'label' => 'SECTION 2', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'description', 'label' => 'DESCRIPTION', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'opening_cans', 'label' => 'OPENING STOCK QTY (CAN)', 'group' => 'Opening Stock', 'type' => 'number'],
        ['key' => 'opening_litres', 'label' => 'OPENING STOCK QTY (LTR)', 'group' => 'Opening Stock', 'type' => 'number'],
        ['key' => 'opening_unit_price', 'label' => 'UNIT PRICE (RM)', 'group' => 'Opening Stock', 'type' => 'money'],
        ['key' => 'opening_total_price', 'label' => 'TOTAL PRICE (RM)', 'group' => 'Opening Stock', 'type' => 'money'],
        ['key' => 'balance_cans', 'label' => 'BALANCE STOCK QTY (CAN)', 'group' => 'Closing Stock', 'type' => 'number'],
        ['key' => 'balance_litres', 'label' => 'BALANCE STOCK QTY (LTR)', 'group' => 'Closing Stock', 'type' => 'number'],
        ['key' => 'closing_unit_price', 'label' => 'UNIT PRICE (RM)', 'group' => 'Closing Stock', 'type' => 'money'],
        ['key' => 'closing_total_price', 'label' => 'TOTAL PRICE (RM)', 'group' => 'Closing Stock', 'type' => 'money'],
        ['key' => 'current_location', 'label' => 'CURRENT LOCATION', 'group' => 'Location', 'type' => 'text'],
        ['key' => 'batch_no', 'label' => 'PAINT Batch No.', 'group' => 'Paint Data', 'type' => 'text'],
        ['key' => 'original_date', 'label' => 'Date of Manufacture - Best Before', 'group' => 'Paint Data', 'type' => 'textarea'],
        ['key' => 'issue_location', 'label' => 'To Location', 'group' => 'Issue Out to Location', 'type' => 'text'],
        ['key' => 'issue_cog', 'label' => 'COG No. & Date', 'group' => 'Issue Out to Location', 'type' => 'textarea'],
        ['key' => 'issue_cans', 'label' => 'Qty (OUT) CAN', 'group' => 'Issue Out to Location', 'type' => 'number'],
        ['key' => 'issue_litres', 'label' => 'Qty (OUT) LTR', 'group' => 'Issue Out to Location', 'type' => 'number'],
        ['key' => 'issue_total_price', 'label' => 'TOTAL PRICE ISSUED (RM)', 'group' => 'Issue Out to Location', 'type' => 'money'],
        ['key' => 'mr_reference', 'label' => 'MR No. & Date', 'group' => 'MR Request', 'type' => 'textarea'],
        ['key' => 'po_reference', 'label' => 'PO No. & Date', 'group' => 'Purchase Order', 'type' => 'textarea'],
        ['key' => 'do_reference', 'label' => 'DO No. & Date', 'group' => 'Purchase Order', 'type' => 'textarea'],
        ['key' => 'unit', 'label' => 'Unit', 'group' => 'Receive (Stock-In)', 'type' => 'text'],
        ['key' => 'stock_in_qty', 'label' => 'Qty (IN)', 'group' => 'Receive (Stock-In)', 'type' => 'number'],
        ['key' => 'storage_rack', 'label' => 'Storage Rack No.', 'group' => 'Receive (Stock-In)', 'type' => 'text'],
        ['key' => 'backload_location', 'label' => 'From Location', 'group' => 'Received Backload', 'type' => 'text'],
        ['key' => 'backload_cog', 'label' => 'COG No. & Date', 'group' => 'Received Backload', 'type' => 'textarea'],
        ['key' => 'backload_qty', 'label' => 'Qty (IN)', 'group' => 'Received Backload', 'type' => 'number'],
        ['key' => 'backload_rack', 'label' => 'Storage Rack No..', 'group' => 'Received Backload', 'type' => 'text'],
        ['key' => 'unfit_report', 'label' => 'UNFIT Report (Inspection) / Damage Report & Date', 'group' => 'Unfit & Write-Off', 'type' => 'textarea'],
        ['key' => 'writeoff_reference', 'label' => 'Write-Off Ref. No. & Date', 'group' => 'Unfit & Write-Off', 'type' => 'textarea'],
    ];

    public static function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $field) {
            if ($field['key'] === 'original_date') continue; // Immutable source date text.
            $rules[$field['key']] = match ($field['type']) {
                'number', 'money' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,'.($field['type'] === 'money' ? 2 : 3)],
                'text' => [in_array($field['key'], ['category', 'description']) ? 'required' : 'nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:10000'],
            };
        }
        return [...$rules,
            'manufacture_date' => ['nullable', 'date_format:Y-m-d'],
            'best_before_date' => ['nullable', 'date_format:Y-m-d'],
            'date_status' => ['required', 'in:unconfirmed,confirmed,not_recorded'],
            'review_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
