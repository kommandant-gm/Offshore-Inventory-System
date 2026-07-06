<?php

namespace App\Actions\Assets;

use App\Domain\Assets\AssetStatusTransition;
use App\Enums\AssetCondition;
use App\Enums\AssetMovementType;
use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordAssetMovementAction
{
    public function execute(Asset $asset, array $validated, int $userId): AssetMovement
    {
        $movementType = AssetMovementType::from($validated['movement_type']);

        if (! AssetStatusTransition::allowed($asset->current_status, $movementType)) {
            throw ValidationException::withMessages([
                'movement_type' => 'This movement type is not allowed for the asset in its current status.',
            ]);
        }

        $nextStatus = AssetStatusTransition::nextStatus($movementType);
        $nextCondition = isset($validated['condition_after']) && $validated['condition_after'] !== null
            ? AssetCondition::from($validated['condition_after'])
            : $asset->current_condition;

        return DB::transaction(function () use ($asset, $validated, $userId, $nextStatus, $nextCondition) {
            $movement = $asset->movements()->create([
                ...$validated,
                'from_status' => $asset->current_status->value,
                'to_status' => $nextStatus->value,
                'condition_before' => $asset->current_condition?->value,
                'condition_after' => $nextCondition?->value,
                'created_by' => $userId,
            ]);

            $asset->update([
                'current_location_id' => $validated['to_location_id'] ?? $asset->current_location_id,
                'current_status' => $nextStatus->value,
                'current_condition' => $nextCondition?->value,
            ]);

            return $movement->load(['asset', 'fromLocation', 'toLocation', 'creator']);
        });
    }
}
