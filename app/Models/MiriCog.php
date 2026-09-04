<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiriCog extends Model
{
    use BelongsToBranch, HasFactory;
    protected $fillable = ['branch_id', 'cog_no', 'movement_type', 'document_date', 'from_location', 'to_location', 'receiver_name', 'receiver_email', 'issued_by_name', 'remarks', 'status', 'signature', 'signed_at', 'signed_ip', 'created_by', 'updated_by'];
    protected function casts(): array { return ['document_date' => 'date', 'signed_at' => 'datetime']; }
    public function items(): HasMany { return $this->hasMany(MiriCogItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
