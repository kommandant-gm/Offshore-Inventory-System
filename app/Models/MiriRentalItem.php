<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiriRentalItem extends Model
{
    use BelongsToBranch, HasFactory;

    protected $table = 'miri_rental_items';

    protected $fillable = [
        'branch_id', 'category', 'section_1', 'section_2', 'description',
        'serial_tag_equipment_no', 'unit', 'supplier', 'project_contract',
        'current_location', 'rental_due_date', 'issue_out_cog_no', 'issue_out_cog_date',
        'received_backload_from_location', 'received_backload_cog_no', 'received_backload_cog_date',
        'offhire_certificate_no', 'offhire_certificate_date', 'return_cog_no', 'return_cog_date',
        'mr_no', 'mr_date', 'po_or_sr_no', 'po_or_sr_date', 'do_no', 'do_date',
        'onhire_certificate_no', 'onhire_certificate_date', 'status', 'remarks', 'active',
    ];

    protected function casts(): array
    {
        return [
            'rental_due_date' => 'date', 'issue_out_cog_date' => 'date',
            'received_backload_cog_date' => 'date', 'offhire_certificate_date' => 'date',
            'return_cog_date' => 'date', 'mr_date' => 'date', 'po_or_sr_date' => 'date',
            'do_date' => 'date', 'onhire_certificate_date' => 'date', 'active' => 'boolean',
        ];
    }
}
