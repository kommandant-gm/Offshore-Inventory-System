<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stocktake_id',
        'inventory_item_id',
        'system_quantity',
        'counted_quantity',
        'variance_quantity',
        'adjustment_transaction_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'variance_quantity' => 'decimal:2',
        ];
    }

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'adjustment_transaction_id');
    }
}
