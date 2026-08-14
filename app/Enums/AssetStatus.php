<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case PendingCheckout = 'pending_checkout';
    case PendingCheckin = 'pending_checkin';
    case InTransit = 'in_transit';
    case Deployed = 'deployed';
    case UnderRepair = 'under_repair';
    case InspectionHold = 'inspection_hold';
    case Damaged = 'damaged';
    case EndOfLife = 'end_of_life';
    case Disposed = 'disposed';

    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            array_filter(self::cases(), fn (self $status) => ! in_array($status, [
                self::InTransit,
                self::InspectionHold,
                self::Damaged,
                self::Disposed,
            ], true)),
        );
    }

    public function label(): string
    {
        return $this === self::EndOfLife
            ? 'End of Life'
            : str($this->value)->replace('_', ' ')->title()->toString();
    }
}
