<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KemamanInventoryItem extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'category',
        'item_description',
        'size_swl',
        'unit',
        'tag_no',
        'total_quantity',
        'quantity_in',
        'quantity_out',
        'available_quantity',
        'location_quantity',
        'damaged_quantity',
        'beyond_repair_quantity',
        'not_traceable_quantity',
        'date_issued',
        'location',
        'document_reference',
        'backload_date',
        'transfer_reference',
        'certificate_no',
        'test_expiry_date',
        'equipment_status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'backload_date' => 'date',
            'test_expiry_date' => 'date',
            'total_quantity' => 'integer',
            'quantity_in' => 'integer',
            'quantity_out' => 'integer',
            'available_quantity' => 'integer',
            'location_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'beyond_repair_quantity' => 'integer',
            'not_traceable_quantity' => 'integer',
        ];
    }
}
