<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStocktakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('movements') ?? false;
    }

    public function rules(): array
    {
        $branchId = app(\App\Services\BranchContext::class)->id($this->user());
        return [
            'stocktake_date' => ['required', 'date'],
            'location_id' => ['required', $branchId ? Rule::exists('locations', 'id')->where('branch_id', $branchId) : Rule::exists('locations', 'id')],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', $branchId ? Rule::exists('inventory_items', 'id')->where('branch_id', $branchId) : Rule::exists('inventory_items', 'id')],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }
}
