<?php

namespace App\Http\Requests;

use App\Support\AccessMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $roleKeys = array_keys(AccessMatrix::roleOptions());
        return [
            'role' => ['required', Rule::in($roleKeys)],
        ];
    }
}
