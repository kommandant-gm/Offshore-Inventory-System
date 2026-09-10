<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMajorEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('assets') ?? false;
    }

    public function rules(): array
    {
        $equipmentId = $this->route('equipment')?->id ?? 0;
        $ownedCertificate = fn () => Rule::exists('miri_inventory_certificates', 'id')->where('miri_inventory_item_id', $equipmentId);
        return [
            'removed_certificate_ids' => ['sometimes', 'array', 'max:100'],
            'removed_certificate_ids.*' => ['integer', 'distinct', $ownedCertificate()],
            'certificates.*.id' => ['nullable', 'integer', 'distinct', $ownedCertificate()],
            'certificates.*.image' => ['bail', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120', function ($attribute, $file, $fail) {
                if ($file->getMimeType() !== 'application/pdf' && \Illuminate\Support\Facades\Validator::make(['file' => $file], ['file' => ['image', 'dimensions:max_width=4096,max_height=4096']])->fails()) {
                    $fail('Images must be valid JPG, PNG or WebP files within 4096 × 4096 pixels.');
                }
            }],
            'certificates.*.remove_image' => ['sometimes', 'boolean'],
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
            'certificates' => ['nullable', 'array', 'max:100'], 'certificates.*.certificate_type' => ['required', 'string', 'max:255'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $files = collect($this->file('certificates', []))->pluck('image')->filter();
            if ($files->count() > 10 || $files->sum(fn ($file) => $file->isValid() ? $file->getSize() : 0) > 20 * 1024 * 1024) {
                $validator->errors()->add('certificates', 'Upload at most 10 files and 20 MB total per save. Save these, then edit again to attach more.');
            }
        });
    }
}
