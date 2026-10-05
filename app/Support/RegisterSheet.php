<?php

namespace App\Support;

use App\Http\Requests\SaveMiriRentalRequest;

class RegisterSheet
{
    public static function columns(string $register): array
    {
        $fields = match ($register) {
            'construction' => array_values(array_filter(ConstructionFields::FIELDS, fn ($field) => $field['type'] !== 'private')),
            'paint' => [...PaintFields::FIELDS,
                ['key' => 'date_status', 'label' => 'Date status', 'type' => 'text'],
                ['key' => 'manufacture_date', 'label' => 'Manufacture date', 'type' => 'date'],
                ['key' => 'best_before_date', 'label' => 'Best before date', 'type' => 'date']],
            'rental' => collect((new SaveMiriRentalRequest)->rules())->except(['uploads', 'uploads.*', 'active', 'company'])
                ->map(fn ($rules, $key) => ['key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'type' => str_ends_with($key, '_date') ? 'date' : 'text'])->values()->all(),
            default => [],
        };
        return array_map(fn ($field) => [...$field, 'readonly' => $register === 'paint' && in_array($field['key'], [
            'opening_cans', 'opening_litres', 'balance_cans', 'balance_litres', 'original_date', 'date_status', 'manufacture_date', 'best_before_date',
        ])], [['key' => 'company', 'label' => 'Company', 'type' => 'text'], ...$fields]);
    }

    public static function rules(string $register): array
    {
        $rules = match ($register) {
            'rental' => (new SaveMiriRentalRequest)->rules(),
            'construction' => ConstructionFields::rules(),
            'paint' => PaintFields::rules(),
            default => [],
        };
        $rules['company'] = ['nullable', 'in:DESB,FTSB'];
        return array_intersect_key($rules, array_flip(array_column(array_filter(self::columns($register), fn ($field) => ! $field['readonly']), 'key')));
    }
}
