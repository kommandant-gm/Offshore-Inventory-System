<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'description',
        'category_id',
        'uom',
        'default_location_id',
        'opening_stock',
        'standard_cost',
        'minimum_stock',
        'rack_no',
        'active',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'opening_stock' => 'decimal:2',
            'standard_cost' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }

    public function latestTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class, 'item_id')
            ->ofMany([
                'transaction_date' => 'max',
                'id' => 'max',
            ]);
    }

    public function locationBalances(): HasMany
    {
        return $this->hasMany(InventoryLocationBalance::class);
    }
}
