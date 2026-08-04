<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailActivityLog extends Model
{
    protected $fillable = ['recipient', 'subject', 'notification_type', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
