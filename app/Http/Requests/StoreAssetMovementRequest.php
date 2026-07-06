<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_date' => ['required', 'date'],
            'asset_id' => ['required', 'exists:assets,id'],
            'movement_type' => ['required', Rule::enum(AssetMovementType::class)],
            'from_location_id' => ['nullable', 'exists:locations,id'],
            'to_location_id' => ['nullable', 'exists:locations,id'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'project_ref' => ['nullable', 'string', 'max:255'],
            'condition_after' => ['nullable', Rule::enum(AssetCondition::class)],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
