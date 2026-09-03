<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiriInventoryCategory extends Model
{
    use BelongsToBranch, HasFactory;

    protected $table = 'miri_inventory_categories';

    protected $fillable = ['branch_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
