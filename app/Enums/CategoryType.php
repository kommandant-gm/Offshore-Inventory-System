<?php

namespace App\Enums;

enum CategoryType: string
{
    case Asset = 'asset';
    case Inventory = 'inventory';
    case Both = 'both';

    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => ucfirst($type->value),
            ],
            self::cases(),
        );
    }
}
