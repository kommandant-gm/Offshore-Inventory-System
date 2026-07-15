<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cog extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'cog_no',
        'document_date',
        'consignee',
        'shipper',
        'destination',
        'vessel',
        'department',
        'receiver_name',
        'receiver_email',
        'cc_emails',
        'receiver_designation',
        'issued_by_name',
        'issued_by_designation',
        'checked_by_name',
        'checked_by_designation',
        'status',
        'approval_token',
        'approval_sent_at',
        'approval_expires_at',
        'approved_at',
        'rejected_at',
        'receiver_remarks',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'cc_emails' => 'array',
            'approval_sent_at' => 'datetime',
            'approval_expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CogItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
