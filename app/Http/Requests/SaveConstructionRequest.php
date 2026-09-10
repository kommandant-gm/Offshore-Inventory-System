<?php

namespace App\Http\Requests;

use App\Support\ConstructionFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveConstructionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canEdit('assets') ?? false; }

    public function rules(): array
    {
        return [
            ...ConstructionFields::rules(),
            'grouping_reviewed' => ['sometimes', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:5000', 'required_if:grouping_reviewed,1'],
            'uploads' => ['sometimes', 'array:certificate,inspection,conformity'],
            'uploads.*' => ['bail', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120', function ($attribute, $file, $fail) {
                if ($file->getMimeType() !== 'application/pdf' && Validator::make(['file' => $file], ['file' => 'image|dimensions:max_width=4096,max_height=4096'])->fails()) {
                    $fail('Use a valid JPG, PNG, WebP or PDF. Maximum image dimensions: 4096 × 4096.');
                }
            }],
            'remove_attachments' => ['sometimes', 'array', 'max:3'],
            'remove_attachments.*' => ['string', 'distinct', Rule::in(array_keys(ConstructionFields::ATTACHMENTS))],
        ];
    }
}
