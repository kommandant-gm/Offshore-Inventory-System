<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
class UpdateCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => CategoryType::Asset->value,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canEdit('categories') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'active' => ['required', 'boolean'],
        ];
    }
}
