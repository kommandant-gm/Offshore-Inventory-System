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
        'assigned_at', 'returned_at', 'assigned_by', 'received_by', 'remarks', 'job_title', 'checkout_status',
        'checkout_token', 'checkout_sent_at', 'signed_at', 'signature', 'signed_ip', 'signed_user_agent',
        'policy_acknowledgments', 'policy_acknowledged_at',
        'checkin_status', 'checkin_token', 'checkin_sent_at', 'checkin_signed_at', 'checkin_signature', 'checkin_signed_ip', 'checkin_signed_user_agent', 'checkin_received_by_email',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'date', 'returned_at' => 'date', 'checkout_sent_at' => 'datetime', 'signed_at' => 'datetime', 'policy_acknowledgments' => 'array', 'policy_acknowledged_at' => 'datetime', 'checkin_sent_at' => 'datetime', 'checkin_signed_at' => 'datetime'];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
