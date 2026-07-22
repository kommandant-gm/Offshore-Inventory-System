<?php

namespace App\Http\Requests;

use App\Services\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('it_assets') ?? false;
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->id($this->user());

        return [
            'license_code' => ['required', 'string', 'max:100', Rule::unique('it_licenses')->where('branch_id', $branchId)],
            'software_name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'license_type' => ['required', Rule::in(['subscription', 'perpetual', 'volume', 'oem', 'trial'])],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'seats_total' => ['required', 'integer', 'min:1', 'max:1000000'],
            'seats_assigned' => ['required', 'integer', 'min:0', 'lte:seats_total'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'auto_renew' => ['required', 'boolean'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'purchase_reference' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
