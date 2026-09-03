<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMiriRentalImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('assets') ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:51200']];
    }
}
