<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case Critical = 'critical';

    public static function options(): array
    {
        return array_map(
            fn (self $condition) => [
                'value' => $condition->value,
                'label' => ucfirst($condition->value),
            ],
            self::cases(),
        );
    }
}
