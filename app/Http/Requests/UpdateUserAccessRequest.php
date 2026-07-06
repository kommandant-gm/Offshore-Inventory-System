<?php

namespace App\Http\Requests;

use App\Support\AccessMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('settings') ?? false;
    }

    public function rules(): array
    {
        $roleKeys = array_keys(AccessMatrix::roleOptions());
        $moduleKeys = array_keys(AccessMatrix::modules());
        $levelKeys = array_keys(AccessMatrix::levelOptions());

        return [
            'role' => ['required', Rule::in($roleKeys)],
            'permissions' => ['required', 'array'],
            'permissions.*' => [Rule::in($levelKeys)],
            ...collect($moduleKeys)->mapWithKeys(fn (string $module) => [
                "permissions.{$module}" => ['required', Rule::in($levelKeys)],
            ])->all(),
        ];
    }
}
