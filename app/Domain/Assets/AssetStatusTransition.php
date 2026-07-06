<?php

namespace App\Domain\Assets;

use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;

class AssetStatusTransition
{
    public static function nextStatus(AssetMovementType $movementType): AssetStatus
    {
        return match ($movementType) {
            AssetMovementType::ReceiveAsset => AssetStatus::Available,
            AssetMovementType::InternalTransfer => AssetStatus::Available,
            AssetMovementType::DeployCheckout => AssetStatus::Deployed,
            AssetMovementType::ReturnIn => AssetStatus::Available,
            AssetMovementType::SendForRepair => AssetStatus::UnderRepair,
            AssetMovementType::ReturnFromRepair => AssetStatus::Available,
            AssetMovementType::MarkDamaged => AssetStatus::Damaged,
            AssetMovementType::InspectionHold => AssetStatus::InspectionHold,
            AssetMovementType::ReleaseFromHold => AssetStatus::Available,
            AssetMovementType::DisposeScrap => AssetStatus::Disposed,
        };
    }

    public static function allowed(AssetStatus $currentStatus, AssetMovementType $movementType): bool
    {
        if ($currentStatus === AssetStatus::Disposed) {
            return $movementType === AssetMovementType::ReceiveAsset;
        }

        return match ($movementType) {
            AssetMovementType::ReceiveAsset => true,
            AssetMovementType::InternalTransfer => in_array($currentStatus, [
                AssetStatus::Available,
                AssetStatus::InspectionHold,
                AssetStatus::Damaged,
            ], true),
            AssetMovementType::DeployCheckout => in_array($currentStatus, [
                AssetStatus::Available,
                AssetStatus::InspectionHold,
            ], true),
            AssetMovementType::ReturnIn => in_array($currentStatus, [
                AssetStatus::Deployed,
                AssetStatus::InTransit,
            ], true),
            AssetMovementType::SendForRepair => in_array($currentStatus, [
                AssetStatus::Available,
                AssetStatus::Damaged,
                AssetStatus::InspectionHold,
            ], true),
            AssetMovementType::ReturnFromRepair => $currentStatus === AssetStatus::UnderRepair,
            AssetMovementType::MarkDamaged => $currentStatus !== AssetStatus::Disposed,
            AssetMovementType::InspectionHold => $currentStatus !== AssetStatus::Disposed,
            AssetMovementType::ReleaseFromHold => $currentStatus === AssetStatus::InspectionHold,
            AssetMovementType::DisposeScrap => $currentStatus !== AssetStatus::Disposed,
        };
    }
}
