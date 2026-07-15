<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('it_assets') ?? false;
    }

    public function rules(): array
    {
        $branchId = app(\App\Services\BranchContext::class)->id($this->user());
        return [
            'asset_tag_no' => ['required', 'string', 'max:100', Rule::unique('assets', 'asset_tag_no')->ignore($this->route('asset'))],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'purchase_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'storage_position' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'string', 'max:50'],
            'ownership' => ['nullable', 'string', 'max:255'],
            'current_location_id' => ['nullable', $branchId ? Rule::exists('locations', 'id')->where('branch_id', $branchId) : Rule::exists('locations', 'id')],
            'current_status' => ['required', Rule::enum(AssetStatus::class)],
            'current_condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
