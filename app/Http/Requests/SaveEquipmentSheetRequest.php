<?php

namespace App\Http\Requests;

use App\Support\EquipmentSheet;

class SaveEquipmentSheetRequest extends SaveMajorEquipmentRequest
{
    public function rules(): array
    {
        $equipmentRules = parent::rules();
        $allowed = implode(',', array_keys(EquipmentSheet::FIELDS));
        $rules = [
            'inventory_type' => ['required', 'in:machinery,cargo'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*' => ['required', 'array:id,changes,original'],
            'rows.*.id' => ['required', 'integer', 'distinct'],
            'rows.*.changes' => ['required', 'array:'.$allowed, 'min:1'],
            'rows.*.original' => ['required', 'array:'.$allowed, 'min:1'],
            'rows.*.original.*' => ['nullable', 'string'],
        ];
        foreach (EquipmentSheet::FIELDS as $key => $label) {
            $rules['rows.*.changes.'.$key] = ['sometimes', ...$equipmentRules[$key]];
        }
        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // This endpoint changes only the submitted cells, not the other fields.
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            foreach ($this->input('rows', []) as $index => $row) {
                $changed = array_keys($row['changes']);
                $original = array_keys($row['original']);
                sort($changed); sort($original);
                if ($changed !== $original) $validator->errors()->add("rows.{$index}.original", 'Original values must be supplied for every changed cell.');
            }
        });
    }
}
