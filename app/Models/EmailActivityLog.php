<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailActivityLog extends Model
{
    protected $fillable = ['recipient', 'subject', 'body', 'details', 'action_url', 'action_label', 'attachment_name', 'notification_type', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'details' => 'array'];
    }
}
