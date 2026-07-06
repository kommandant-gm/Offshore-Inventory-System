<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CogApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_designation' => ['nullable', 'string', 'max:255'],
            'receiver_remarks' => ['nullable', 'string'],
        ];
    }
}
