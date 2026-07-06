<?php

namespace App\Http\Requests;

use App\Enums\InventoryTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEdit('movements') ?? false;
    }

    public function rules(): array
    {
        $requiresCogDetails = fn () => $this->boolean('generate_cog')
            && $this->input('transaction_type') === InventoryTransactionType::Issue->value;

        return [
            'transaction_date' => ['required', 'date'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'source_location_id' => ['nullable', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'exists:locations,id'],
            'transaction_type' => ['required', Rule::enum(InventoryTransactionType::class)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'po_no' => ['nullable', 'string', 'max:255'],
            'do_no' => ['nullable', 'string', 'max:255'],
            'cog_issued_out' => ['nullable', 'string', 'max:255'],
            'cog_received' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'generate_cog' => ['nullable', 'boolean'],
            'cog' => ['nullable', 'array'],
            'cog.document_date' => ['nullable', Rule::requiredIf($requiresCogDetails), 'date'],
            'cog.consignee' => ['nullable', 'string', 'max:255'],
            'cog.shipper' => ['nullable', 'string', 'max:255'],
            'cog.destination' => ['nullable', 'string', 'max:255'],
            'cog.vessel' => ['nullable', 'string', 'max:255'],
            'cog.department' => ['nullable', 'string', 'max:255'],
            'cog.receiver_name' => ['nullable', 'string', 'max:255'],
            'cog.receiver_email' => ['nullable', 'email', 'max:255'],
            'cog.cc_emails' => ['nullable', 'array'],
            'cog.cc_emails.*' => ['email', 'max:255'],
            'cog.receiver_designation' => ['nullable', 'string', 'max:255'],
            'cog.issued_by_name' => ['nullable', 'string', 'max:255'],
            'cog.issued_by_designation' => ['nullable', 'string', 'max:255'],
            'cog.checked_by_name' => ['nullable', 'string', 'max:255'],
            'cog.checked_by_designation' => ['nullable', 'string', 'max:255'],
            'cog.measurement_cu_metre' => ['nullable', 'numeric', 'min:0'],
            'cog.gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cog.po_no' => ['nullable', 'string', 'max:255'],
            'cog.ex_location' => ['nullable', 'string', 'max:255'],
            'cog.serial_no' => ['nullable', 'string', 'max:255'],
            'cog.item_remarks' => ['nullable', 'string'],
            'cog.remarks' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->boolean('generate_cog') && $this->input('transaction_type') !== InventoryTransactionType::Issue->value) {
                    $validator->errors()->add('generate_cog', 'COG can only be generated for issue movements.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'generate_cog' => $this->boolean('generate_cog'),
            'cog' => [
                ...($this->input('cog') ?? []),
                'cc_emails' => $this->normalizeEmails(data_get($this->input('cog'), 'cc_emails')),
            ],
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
