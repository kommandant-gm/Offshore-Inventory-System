<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStocktakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('movements') ?? false;
    }

    public function rules(): array
    {
        return [
            'stocktake_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }
}
