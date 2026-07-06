<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_tag_no' => ['required', 'string', 'max:100', Rule::unique('assets', 'asset_tag_no')],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'string', 'max:50'],
            'ownership' => ['nullable', 'string', 'max:255'],
            'current_location_id' => ['nullable', 'exists:locations,id'],
            'current_status' => ['required', Rule::enum(AssetStatus::class)],
            'current_condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
