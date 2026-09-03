<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MajorEquipment extends Model
{
    use BelongsToBranch, HasFactory;

    protected $table = 'miri_inventory_items';

    protected $fillable = [
        'branch_id', 'category', 'section_1', 'section_2', 'description', 'unit',
        'model_brand', 'serial_no', 'tag_no', 'current_location', 'status',
        'issue_out_location', 'issue_out_cog_no', 'issue_out_cog_date',
        'received_backload_cog_no', 'received_backload_cog_date', 'mr_request',
        'purchase_order', 'delivery_order', 'supplier', 'unfit_report',
        'write_off_reference', 'remarks', 'active',
    ];

    protected function casts(): array
    {
        return [
            'issue_out_cog_date' => 'date',
            'received_backload_cog_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(MajorEquipmentCertificate::class, 'miri_inventory_item_id');
    }
}
