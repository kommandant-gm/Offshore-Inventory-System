<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMajorEquipmentImportRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canEdit('assets') ?? false; }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt']];
    }
}
