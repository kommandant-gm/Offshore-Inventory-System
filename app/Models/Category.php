<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'code',
        'name',
        'type',
        'active',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'active' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
