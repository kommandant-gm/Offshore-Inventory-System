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

    protected $hidden = ['normalized_tag'];

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
        // Compute duplicate groups once, not a full-table comparison for each row.
        $groups = self::withoutGlobalScopes()->select('branch_id', 'normalized_tag')
            ->whereNotNull('normalized_tag')->groupBy('branch_id', 'normalized_tag')
            ->havingRaw('COUNT(*) > 1');

        return $query->whereIn(
            \Illuminate\Support\Facades\DB::raw('(miri_inventory_items.branch_id, miri_inventory_items.normalized_tag)'),
            $groups,
        );
    }

    public function scopeWithDuplicateCount($query)
    {
        $groups = self::withoutGlobalScopes()->select('branch_id', 'normalized_tag')->selectRaw('COUNT(*) as tag_count')
            ->whereNotNull('normalized_tag')->groupBy('branch_id', 'normalized_tag');

        if ($query->getQuery()->columns === null) $query->select('miri_inventory_items.*');

        return $query->leftJoinSub($groups, 'miri_tag_counts', fn ($join) => $join
            ->on('miri_tag_counts.branch_id', '=', 'miri_inventory_items.branch_id')
            ->on('miri_tag_counts.normalized_tag', '=', 'miri_inventory_items.normalized_tag'))
            ->selectRaw('COALESCE(miri_tag_counts.tag_count, 0) as duplicate_count');
    }
}
