<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('it_assets') ?? false;
    }

    public function rules(): array
    {
        $branchId = app(\App\Services\BranchContext::class)->id($this->user());

        return [
            'asset_ids' => ['required', 'array', 'min:1', 'max:100'],
            'asset_ids.*' => ['integer', 'distinct'],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'current_location_id' => ['sometimes', 'nullable', $branchId ? Rule::exists('locations', 'id')->where('branch_id', $branchId) : Rule::exists('locations', 'id')],
            'current_condition' => ['sometimes', 'nullable', Rule::enum(AssetCondition::class)],
            'operating_system' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purchase_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'ownership' => ['sometimes', 'nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fields = ['category_id', 'current_location_id', 'current_condition', 'operating_system', 'purchase_year', 'ownership', 'active', 'remarks'];
            if (! collect($fields)->contains(fn ($field) => $this->exists($field))) {
                $validator->errors()->add('asset_ids', 'Choose at least one field to update.');
            }
        });
    }
}
