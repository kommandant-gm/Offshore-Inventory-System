<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'asset_id', 'assigned_to_name', 'assigned_email', 'employee_id', 'department',
        'assigned_at', 'returned_at', 'assigned_by', 'received_by', 'remarks', 'checkout_status',
        'checkout_token', 'checkout_sent_at', 'signed_at', 'signature', 'signed_ip', 'signed_user_agent',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'date', 'returned_at' => 'date', 'checkout_sent_at' => 'datetime', 'signed_at' => 'datetime'];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
