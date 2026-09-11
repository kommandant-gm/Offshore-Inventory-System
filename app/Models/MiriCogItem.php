<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiriCogItem extends Model
{
    use BelongsToBranch, HasFactory;
    protected $fillable = ['size_model','serial_no','batch_no','mr_reference', 'miri_cog_id', 'branch_id', 'item_type', 'item_id', 'identifier', 'description', 'quantity', 'unit', 'current_location', 'remarks'];
    protected function casts(): array { return ['quantity' => 'decimal:3']; }
    public function cog(): BelongsTo { return $this->belongsTo(MiriCog::class, 'miri_cog_id'); }
}
