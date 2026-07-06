<?php

namespace App\Enums;

enum LocationType: string
{
    case Yard = 'yard';
    case Rack = 'rack';
    case Bin = 'bin';
    case Offshore = 'offshore';
    case Workshop = 'workshop';
    case Vendor = 'vendor';
    case Scrap = 'scrap';
    case Transit = 'transit';

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
