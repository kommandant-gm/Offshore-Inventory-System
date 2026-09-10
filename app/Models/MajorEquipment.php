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
        'inventory_type', 'size_model', 'size_ton', 'size_length', 'quantity', 'import_warnings', 'source_values',
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
            'quantity' => 'decimal:2', 'import_warnings' => 'array', 'source_values' => 'array',
            'issue_out_cog_date' => 'date',
            'received_backload_cog_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(MajorEquipmentCertificate::class, 'miri_inventory_item_id');
    }

    public function scopeMissingDetails($query)
    {
        return $query->where(function ($query) {
            foreach (['tag_no', 'description', 'current_location'] as $column) {
                $query->orWhereNull($column)->orWhereRaw("TRIM({$column}) = ''");
            }
        });
    }

    public function scopeDuplicateTag($query)
    {
        return $query->whereRaw("TRIM(COALESCE(miri_inventory_items.tag_no, '')) <> ''")
            ->whereRaw('(SELECT COUNT(*) FROM miri_inventory_items AS peers WHERE peers.branch_id = miri_inventory_items.branch_id AND LOWER(TRIM(peers.tag_no)) = LOWER(TRIM(miri_inventory_items.tag_no))) > 1');
    }

    public function scopeWithDuplicateCount($query)
    {
        return $query->addSelect(['duplicate_count' => self::withoutGlobalScopes()->from('miri_inventory_items as peers')
            ->selectRaw('COUNT(*)')->whereColumn('peers.branch_id', 'miri_inventory_items.branch_id')
            ->whereRaw("TRIM(COALESCE(miri_inventory_items.tag_no, '')) <> ''")
            ->whereRaw('LOWER(TRIM(peers.tag_no)) = LOWER(TRIM(miri_inventory_items.tag_no))')]);
    }
}
