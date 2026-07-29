<?php

namespace App\Http\Requests;

use App\Services\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKemamanInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = app(BranchContext::class)->branch($this->user());

        return $branch?->code === 'KEMAMAN' && ($this->user()?->canEdit('assets') ?? false);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:120'],
            'item_description' => ['required', 'string', 'max:255'],
            'size_swl' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'tag_no' => ['nullable', 'string', 'max:150'],
            'total_quantity' => ['required', 'integer', 'min:0'],
            'quantity_in' => ['required', 'integer', 'min:0'],
            'quantity_out' => ['required', 'integer', 'min:0'],
            'available_quantity' => ['required', 'integer', 'min:0'],
            'location_quantity' => ['required', 'integer', 'min:0'],
            'damaged_quantity' => ['required', 'integer', 'min:0'],
            'beyond_repair_quantity' => ['required', 'integer', 'min:0'],
            'not_traceable_quantity' => ['required', 'integer'],
            'date_issued' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:150'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'backload_date' => ['nullable', 'date'],
            'transfer_reference' => ['nullable', 'string', 'max:255'],
            'certificate_no' => ['nullable', 'string', 'max:180'],
            'test_expiry_date' => ['nullable', 'date'],
            'equipment_status' => ['required', Rule::in([
                'available', 'in_use', 'under_inspection', 'damaged', 'beyond_repair', 'not_traceable',
            ])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
