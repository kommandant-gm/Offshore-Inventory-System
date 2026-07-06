<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case InTransit = 'in_transit';
    case Deployed = 'deployed';
    case UnderRepair = 'under_repair';
    case InspectionHold = 'inspection_hold';
    case Damaged = 'damaged';
    case Disposed = 'disposed';

    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => str($status->value)->replace('_', ' ')->title()->toString(),
            ],
            self::cases(),
        );
    }
}
