<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cog_id',
        'inventory_item_id',
        'qty',
        'unit',
        'part_no',
        'full_description',
        'measurement_cu_metre',
        'gross_weight_kg',
        'po_no',
        'ex_location',
        'serial_no',
        'unit_price',
        'total_amount',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'measurement_cu_metre' => 'decimal:3',
            'gross_weight_kg' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function cog(): BelongsTo
    {
        return $this->belongsTo(Cog::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
