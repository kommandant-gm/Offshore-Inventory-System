<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMiriRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('assets') ?? false;
    }

    public function rules(): array
    {
        return [
            'company' => ['nullable', 'in:DESB,FTSB'],
            'category' => ['nullable', 'string', 'max:255'], 'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'], 'serial_tag_equipment_no' => ['nullable', 'string', 'max:255'], 'unit' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'], 'project_contract' => ['nullable', 'string', 'max:255'], 'current_location' => ['nullable', 'string', 'max:255'],
            'rental_due_date' => ['nullable', 'date'], 'issue_out_cog_no' => ['nullable', 'string', 'max:255'], 'issue_out_cog_date' => ['nullable', 'date'],
            'received_backload_from_location' => ['nullable', 'string', 'max:255'], 'received_backload_cog_no' => ['nullable', 'string', 'max:255'], 'received_backload_cog_date' => ['nullable', 'date'],
            'offhire_certificate_no' => ['nullable', 'string', 'max:255'], 'offhire_certificate_date' => ['nullable', 'date'], 'return_cog_no' => ['nullable', 'string', 'max:255'], 'return_cog_date' => ['nullable', 'date'],
            'mr_no' => ['nullable', 'string', 'max:255'], 'mr_date' => ['nullable', 'date'], 'po_or_sr_no' => ['nullable', 'string', 'max:255'], 'po_or_sr_date' => ['nullable', 'date'],
            'do_no' => ['nullable', 'string', 'max:255'], 'do_date' => ['nullable', 'date'], 'onhire_certificate_no' => ['nullable', 'string', 'max:255'], 'onhire_certificate_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:On Hire,Issued,Received Backload,Off Hire,Returned to Supplier,Overdue'], 'remarks' => ['nullable', 'string'], 'active' => ['nullable', 'boolean'],
        ];
    }
}
