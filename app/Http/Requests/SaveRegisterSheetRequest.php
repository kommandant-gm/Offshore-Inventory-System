<?php

namespace App\Http\Requests;

use App\Support\RegisterSheet;

class SaveRegisterSheetRequest extends SaveEquipmentSheetRequest
{
    public function rules(): array
    {
        $fields = RegisterSheet::rules($this->route('register'));
        $allowed = implode(',', array_keys($fields));
        $rules = [
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*' => ['required', 'array:id,changes,original'],
            'rows.*.id' => ['required', 'integer', 'distinct'],
            'rows.*.changes' => ['required', 'array:'.$allowed, 'min:1'],
            'rows.*.original' => ['required', 'array:'.$allowed, 'min:1'],
            'rows.*.original.*' => ['nullable', 'string'],
        ];
        foreach ($fields as $key => $fieldRules) $rules['rows.*.changes.'.$key] = ['sometimes', ...$fieldRules];
        return $rules;
    }
}
