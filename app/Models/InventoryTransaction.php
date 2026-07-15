<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'transaction_date',
        'item_id',
        'location_id',
        'source_location_id',
        'destination_location_id',
        'transaction_type',
        'quantity',
        'unit_cost',
        'total_value',
        'po_no',
        'do_no',
        'cog_id',
        'cog_issued_out',
        'cog_received',
        'remarks',
        'created_by',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'transaction_type' => InventoryTransactionType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cog(): BelongsTo
    {
        return $this->belongsTo(Cog::class);
    }
}
