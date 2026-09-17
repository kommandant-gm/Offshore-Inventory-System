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
    protected $fillable = ['consignee_name','consignee_department','from_department','copy_to','destination','issued_designation','verified_by_name','verified_designation','receiver_designation','issued_date','verified_date','received_date', 'branch_id', 'cog_no', 'movement_type', 'document_date', 'from_location', 'to_location', 'receiver_name', 'receiver_email', 'issued_by_name', 'remarks', 'status', 'signature', 'signed_at', 'signed_ip', 'created_by', 'updated_by'];
    protected $appends = ['display_cog_no'];

    public function getDisplayCogNoAttribute(): string
    {
        $number = (string) $this->cog_no;
        if (preg_match('/^MIRI-COG-([0-9]{4})-([0-9]+)$/D', $number, $matches)) {
            return 'DESB/'.substr($matches[1], -2).'/'.str_pad(ltrim($matches[2], '0') ?: '0', 3, '0', STR_PAD_LEFT);
        }
        return $number;
    }

    protected function casts(): array { return ['issued_date' => 'date:Y-m-d', 'verified_date' => 'date:Y-m-d', 'received_date' => 'date:Y-m-d', 'document_date' => 'date:Y-m-d', 'signed_at' => 'datetime']; }
    public function items(): HasMany { return $this->hasMany(MiriCogItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
