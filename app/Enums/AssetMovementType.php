<?php

namespace App\Enums;

enum AssetMovementType: string
{
    case ReceiveAsset = 'receive_asset';
    case InternalTransfer = 'internal_transfer';
    case DeployCheckout = 'deploy_checkout';
    case ReturnIn = 'return_in';
    case SendForRepair = 'send_for_repair';
    case ReturnFromRepair = 'return_from_repair';
    case MarkDamaged = 'mark_damaged';
    case InspectionHold = 'inspection_hold';
    case ReleaseFromHold = 'release_from_hold';
    case DisposeScrap = 'dispose_scrap';

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
