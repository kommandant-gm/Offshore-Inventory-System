<?php
namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\PaintFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MiriPaintItem extends Model
{
    use \App\Models\Concerns\HasInventoryCompany;
    use BelongsToBranch;
    protected $guarded = ['id'];
    protected $hidden = ['source_values', 'match_key'];

    protected function casts(): array
    {
        $casts = ['source_values' => 'encrypted:array', 'import_warnings' => 'array', 'needs_review' => 'boolean',
            'manufacture_date' => 'date:Y-m-d', 'best_before_date' => 'date:Y-m-d', 'unconfirmed_date' => 'date:Y-m-d'];
        foreach (PaintFields::FIELDS as $field) if (in_array($field['type'], ['number', 'money'])) $casts[$field['key']] = 'decimal:'.($field['type'] === 'money' ? 2 : 3);
        return $casts;
    }
    protected static function booted(): void
    {
        static::creating(function ($item) { $item->stock_period ??= app(\App\Services\PaintStockLedger::class)->period(); });
        static::saving(function ($item) {
            $item->match_key = self::matchingKey($item->description, $item->batch_no, $item->current_location);
            $item->needs_review = count($item->reviewFlags()) > 0;
        });
    }
    public static function matchingKey(?string $description, ?string $batch, ?string $location): ?string
    {
        if (blank($description) || blank($batch)) return null;
        return hash('sha256', json_encode(array_map(fn ($v) => mb_strtolower(trim((string) $v)), [$description, $batch, $location])));
    }
    public function reviewFlags(): array
    {
        $flags = array_values($this->import_warnings ?? []);
        if ($this->date_status === 'unconfirmed') $flags[] = 'Date type needs confirmation; excluded from expiry alerts.';
        if (blank($this->description)) $flags[] = 'Description not recorded.';
        if (blank($this->batch_no)) $flags[] = 'Batch number not recorded.';
        if (blank($this->current_location)) $flags[] = 'Current location not recorded.';
        if ($this->balance_cans === null && $this->balance_litres === null) $flags[] = 'Closing balance not recorded; not assumed to be zero.';
        return $flags;
    }
    public function scopeDuplicateBatch($query)
    {
        $groups = self::withoutGlobalScopes()->select('branch_id', 'match_key')->whereNotNull('match_key')
            ->groupBy('branch_id', 'match_key')->havingRaw('COUNT(*) > 1');
        return $query->whereIn(DB::raw('(miri_paint_items.branch_id, miri_paint_items.match_key)'), $groups);
    }
    public function scopeWithDuplicateCount($query)
    {
        $groups = self::withoutGlobalScopes()->select('branch_id', 'match_key')->selectRaw('COUNT(*) as batch_count')
            ->whereNotNull('match_key')->groupBy('branch_id', 'match_key');
        if ($query->getQuery()->columns === null) $query->select('miri_paint_items.*');
        return $query->leftJoinSub($groups, 'paint_batch_counts', fn ($join) => $join
            ->on('paint_batch_counts.branch_id', '=', 'miri_paint_items.branch_id')->on('paint_batch_counts.match_key', '=', 'miri_paint_items.match_key'))
            ->selectRaw('COALESCE(paint_batch_counts.batch_count, 0) as duplicate_count');
    }
    public function scopeExpiryEligible($query)
    {
        return $query->where('date_status', 'confirmed')->whereNotNull('best_before_date');
    }
}
