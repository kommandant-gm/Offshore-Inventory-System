<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('locations') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('locations', 'id')->where('branch_id', app(\App\Services\BranchContext::class)->id($this->user())), 'different:location'],
            'active' => ['required', 'boolean'],
        ];
    }
}
