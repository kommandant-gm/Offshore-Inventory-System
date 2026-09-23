<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\ConstructionFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MiriConstructionItem extends Model
{
    use \App\Models\Concerns\HasInventoryCompany;
    use BelongsToBranch;

    protected $table = 'miri_construction_items';
    protected $guarded = ['id', 'normalized_tag'];
    protected $hidden = ['normalized_tag', 'personnel_details', 'source_values', 'attachments'];

    protected function casts(): array
    {
        $casts = ['personnel_details' => 'encrypted', 'source_values' => 'encrypted:array', 'import_warnings' => 'array', 'attachments' => 'array',
            'certificate_due_date' => 'date:Y-m-d', 'grouping_review_required' => 'boolean', 'grouping_reviewed' => 'boolean', 'needs_review' => 'boolean'];
        foreach (ConstructionFields::FIELDS as $field) if ($field['type'] === 'number') $casts[$field['key']] = 'decimal:'.(in_array($field['key'], ['unit_price', 'closing_value']) ? '2' : '3');
        return $casts;
    }

    public function reviewFlags(): array
    {
        $flags = array_values($this->import_warnings ?? []);
        if (blank($this->description)) $flags[] = 'Description not recorded.';
        if (blank($this->tag_no) && $this->stock_balance === null) $flags[] = 'Untagged row without a recorded stock balance.';
        if ($this->stock_balance !== null && blank($this->unit)) $flags[] = 'Stock balance has no recorded unit.';
        if ($this->grouping_review_required && ! $this->grouping_reviewed) $flags[] = 'Possible stock-history rows: review grouping. Nothing has been merged.';
        return $flags;
    }

    public function scopeDuplicateTag($query)
    {
        $groups = self::withoutGlobalScopes()->select('branch_id', 'normalized_tag')->whereNotNull('normalized_tag')
            ->groupBy('branch_id', 'normalized_tag')->havingRaw('COUNT(*) > 1');
        return $query->whereIn(DB::raw('(miri_construction_items.branch_id, miri_construction_items.normalized_tag)'), $groups);
    }

    public function scopeWithDuplicateCount($query)
    {
        $groups = self::withoutGlobalScopes()->select('branch_id', 'normalized_tag')->selectRaw('COUNT(*) as tag_count')
            ->whereNotNull('normalized_tag')->groupBy('branch_id', 'normalized_tag');
        if ($query->getQuery()->columns === null) $query->select('miri_construction_items.*');
        return $query->leftJoinSub($groups, 'construction_tag_counts', fn ($join) => $join
            ->on('construction_tag_counts.branch_id', '=', 'miri_construction_items.branch_id')
            ->on('construction_tag_counts.normalized_tag', '=', 'miri_construction_items.normalized_tag'))
            ->selectRaw('COALESCE(construction_tag_counts.tag_count, 0) as duplicate_count');
    }
}
