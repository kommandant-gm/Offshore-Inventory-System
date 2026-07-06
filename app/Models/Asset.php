<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag_no',
        'description',
        'category_id',
        'brand',
        'model',
        'serial_no',
        'year',
        'ownership',
        'current_location_id',
        'current_status',
        'current_condition',
        'acquisition_date',
        'acquisition_cost',
        'active',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'current_status' => AssetStatus::class,
            'current_condition' => AssetCondition::class,
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('movement_date')->latest('id');
    }
}
