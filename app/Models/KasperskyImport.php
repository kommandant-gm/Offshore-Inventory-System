<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class KasperskyImport extends Model
{
    use BelongsToBranch;

    protected $fillable = ['filename', 'user_id', 'rows'];

    protected function casts(): array
    {
        return ['rows' => 'array'];
    }

    public static function overview(): array
    {
        $latest = static::query()->latest('id')->first();
        $rows = collect($latest?->rows ?? []);
        $assigned = $rows->filter(fn ($row) => $row['device'] !== '');

        return [
            'total' => $rows->count(),
            'assigned' => $assigned->count(),
            'available' => $rows->count() - $assigned->count(),
            'updated_at' => $latest?->created_at?->toIso8601String(),
            'filename' => $latest?->filename,
            'rows' => $rows->all(),
        ];
    }
}
