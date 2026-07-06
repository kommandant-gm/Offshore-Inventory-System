<?php

namespace App\Http\Requests;

use App\Enums\LocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('locations') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => ['nullable', 'exists:locations,id'],
            'active' => ['required', 'boolean'],
        ];
    }
}
