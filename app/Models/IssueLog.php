<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['level', 'message', 'exception_class', 'file', 'line', 'method', 'url', 'user_id', 'ip_address', 'stack_trace', 'context', 'created_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
