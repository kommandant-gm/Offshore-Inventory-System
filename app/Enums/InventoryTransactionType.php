<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Opening = 'opening';
    case Receive = 'receive';
    case Issue = 'issue';
    case InterlocTransfer = 'interloc_transfer';
    case MaterialReturn = 'material_return';
    case PhysicalAdjustment = 'physical_adjustment';
    case PriceAdjustment = 'price_adjustment';
    case OtherMisc = 'other_misc';

    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => str($type->value)->replace('_', ' ')->title()->toString(),
            ],
            self::cases(),
        );
    }
}
