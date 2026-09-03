<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorEquipmentCertificate extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'miri_inventory_item_id', 'branch_id', 'certificate_type', 'certificate_no',
        'issue_date', 'expiry_date', 'raw_value',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(MajorEquipment::class, 'miri_inventory_item_id');
    }
}
