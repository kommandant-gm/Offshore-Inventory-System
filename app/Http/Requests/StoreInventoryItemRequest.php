<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('assets') ?? false;
    }

    public function rules(): array
    {
        $branchId = app(\App\Services\BranchContext::class)->id($this->user());
        return [
            'item_code' => ['required', 'string', 'max:100', $branchId ? Rule::unique('inventory_items', 'item_code')->where('branch_id', $branchId) : Rule::unique('inventory_items', 'item_code')],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'uom' => ['required', 'string', 'max:20'],
            'default_location_id' => ['nullable', $branchId ? Rule::exists('locations', 'id')->where('branch_id', $branchId) : Rule::exists('locations', 'id')],
            'opening_stock' => ['required', 'numeric', 'min:0'],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'rack_no' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
