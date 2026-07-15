<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            if (! auth()->check()) return;
            $id = app(BranchContext::class)->id();
            if ($id) $query->where($query->qualifyColumn('branch_id'), $id);
        });

        static::creating(function ($model) {
            $model->branch_id ??= app(BranchContext::class)->id()
                ?? Branch::query()->where('code', 'MIRI')->value('id');
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
