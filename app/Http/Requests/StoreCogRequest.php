<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('cogs') ?? false;
    }

    public function rules(): array
    {
        return [
            'document_date' => ['required', 'date'],
            'consignee' => ['nullable', 'string', 'max:255'],
            'shipper' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'vessel' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_email' => ['nullable', 'email', 'max:255'],
            'cc_emails' => ['nullable', 'array'],
            'cc_emails.*' => ['email', 'max:255'],
            'receiver_designation' => ['nullable', 'string', 'max:255'],
            'issued_by_name' => ['nullable', 'string', 'max:255'],
            'issued_by_designation' => ['nullable', 'string', 'max:255'],
            'checked_by_name' => ['nullable', 'string', 'max:255'],
            'checked_by_designation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.part_no' => ['nullable', 'string', 'max:255'],
            'items.*.full_description' => ['required', 'string', 'max:255'],
            'items.*.measurement_cu_metre' => ['nullable', 'numeric', 'min:0'],
            'items.*.gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.po_no' => ['nullable', 'string', 'max:255'],
            'items.*.ex_location' => ['nullable', 'string', 'max:255'],
            'items.*.serial_no' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_amount' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cc_emails' => $this->normalizeEmails($this->input('cc_emails')),
        ]);
    }

    private function normalizeEmails(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            preg_split('/[\s,;]+/', $value) ?: [],
        )));
    }
}
