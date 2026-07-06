<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_date',
        'asset_id',
        'movement_type',
        'from_location_id',
        'to_location_id',
        'from_status',
        'to_status',
        'condition_before',
        'condition_after',
        'requested_by',
        'handled_by',
        'approved_by',
        'reference_no',
        'project_ref',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'movement_type' => AssetMovementType::class,
            'from_status' => AssetStatus::class,
            'to_status' => AssetStatus::class,
            'condition_before' => AssetCondition::class,
            'condition_after' => AssetCondition::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
