<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItLicense extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'license_code',
        'software_name',
        'vendor',
        'license_type',
        'license_key',
        'seats_total',
        'seats_assigned',
        'assigned_to',
        'department',
        'purchase_date',
        'expiry_date',
        'auto_renew',
        'renewal_cost',
        'supplier',
        'purchase_reference',
        'active',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'license_key' => 'encrypted',
            'seats_total' => 'integer',
            'seats_assigned' => 'integer',
            'purchase_date' => 'date',
            'expiry_date' => 'date',
            'auto_renew' => 'boolean',
            'renewal_cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function status(): string
    {
        if (! $this->active) {
            return 'inactive';
        }

        if ($this->expiry_date?->lt(today())) {
            return 'expired';
        }

        if ($this->expiry_date?->lte(today()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'active';
    }
}
