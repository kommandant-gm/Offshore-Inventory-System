<?php

namespace App\Support;

class ConstructionFields
{
    public const FIELDS = [
        ['key' => 'category', 'label' => 'CATEGORY', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'section_1', 'label' => 'SECTION 1', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'section_2', 'label' => 'SECTION 2', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'description', 'label' => 'DESCRIPTION', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'model_brand', 'label' => 'MODEL (BRAND)', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'serial_no', 'label' => 'S/NO', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'tag_no', 'label' => 'TAG NO (ID NO)', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'stock_balance', 'label' => 'STOCK BALANCE QTY', 'group' => 'Category & Section', 'type' => 'number'],
        ['key' => 'status', 'label' => 'STATUS', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'current_location', 'label' => 'CURRENT LOCATION', 'group' => 'Category & Section', 'type' => 'text'],
        ['key' => 'certificate_reference', 'label' => 'CERTIFICATE', 'group' => 'Certificate', 'type' => 'textarea'],
        ['key' => 'certificate_due_date', 'label' => 'DUE DATE', 'group' => 'Certificate', 'type' => 'date'],
        ['key' => 'issue_location', 'label' => 'To Location', 'group' => 'Issue Out to Location', 'type' => 'text'],
        ['key' => 'issue_location_cog', 'label' => 'COG No. & Date', 'group' => 'Issue Out to Location', 'type' => 'textarea'],
        ['key' => 'issue_location_qty', 'label' => 'Qty (OUT)', 'group' => 'Issue Out to Location', 'type' => 'number'],
        ['key' => 'personnel_details', 'label' => 'To Personnel & IC', 'group' => 'Issue Out to Personnel', 'type' => 'private'],
        ['key' => 'issue_personnel_cog', 'label' => 'COG No. & Date', 'group' => 'Issue Out to Personnel', 'type' => 'textarea'],
        ['key' => 'issue_personnel_qty', 'label' => 'Qty (OUT)', 'group' => 'Issue Out to Personnel', 'type' => 'number'],
        ['key' => 'remarks', 'label' => 'Remarks', 'group' => 'Issue Out to Personnel', 'type' => 'textarea'],
        ['key' => 'lifting_inspection', 'label' => 'LIFTING CERTIFICATE & EXPIRY (INSPECTION)', 'group' => 'Certification', 'type' => 'textarea'],
        ['key' => 'lifting_conformity', 'label' => 'LIFTING CERTIFICATE (CERTIFICATE OF CONFORMITY)', 'group' => 'Certification', 'type' => 'textarea'],
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
        ['key' => 'unit_price', 'label' => 'Unit Price (RM)', 'group' => 'Unfit & Write-Off', 'type' => 'number'],
        ['key' => 'closing_value', 'label' => 'Closing Stock Value (RM)', 'group' => 'Unfit & Write-Off', 'type' => 'number'],
    ];

    public const ATTACHMENTS = ['certificate' => 'Certificate', 'inspection' => 'Lifting inspection certificate', 'conformity' => 'Certificate of conformity'];

    public static function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'number' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,'.(in_array($field['key'], ['unit_price', 'closing_value']) ? '2' : '3')],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'text' => [$field['key'] === 'category' ? 'required' : 'nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:10000'],
            };
        }
        return $rules;
    }
}
