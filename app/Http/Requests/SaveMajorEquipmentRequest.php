<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMajorEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('assets') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_type' => ['required', 'in:machinery,cargo'],
            'size_model' => ['nullable', 'string', 'max:255'], 'size_ton' => ['nullable', 'string', 'max:255'],
            'size_length' => ['nullable', 'string', 'max:255'], 'quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'category' => ['required', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'], 'unit' => ['nullable', 'string', 'max:255'],
            'model_brand' => ['nullable', 'string', 'max:255'], 'serial_no' => ['nullable', 'string', 'max:255'],
            'tag_no' => ['nullable', 'string', 'max:255'], 'current_location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'], 'issue_out_location' => ['nullable', 'string', 'max:255'],
            'issue_out_cog_no' => ['nullable', 'string', 'max:255'], 'issue_out_cog_date' => ['nullable', 'date'],
            'received_backload_cog_no' => ['nullable', 'string', 'max:255'], 'received_backload_cog_date' => ['nullable', 'date'],
            'mr_request' => ['nullable', 'string', 'max:255'], 'purchase_order' => ['nullable', 'string', 'max:255'],
            'delivery_order' => ['nullable', 'string', 'max:255'], 'supplier' => ['nullable', 'string', 'max:255'],
            'unfit_report' => ['nullable', 'string'], 'write_off_reference' => ['nullable', 'string'], 'remarks' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'certificates' => ['nullable', 'array'], 'certificates.*.certificate_type' => ['required', 'string', 'max:255'],
            'certificates.*.certificate_no' => ['nullable', 'string', 'max:255'], 'certificates.*.issue_date' => ['nullable', 'date'],
            'certificates.*.expiry_date' => ['nullable', 'date'], 'certificates.*.raw_value' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('inventory_type', $this->route('equipment')?->inventory_type ?? 'machinery');
        $section = trim((string) $this->input('section_1'));
        $this->merge(['inventory_type' => $type, 'section_1' => $type === 'cargo' ? 'CARGO SET' : (in_array(strtoupper($section), ['MACHINARY', 'MACHINERY'], true) ? 'Machinery' : $section)]);
    }
}
