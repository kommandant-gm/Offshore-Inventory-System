<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $module,
        string $event,
        string $summary,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?User $user = null,
        ?Request $request = null,
    ): void {
        AuditLog::query()->create([
            'branch_id' => $auditable?->getAttribute('branch_id') ?? app(BranchContext::class)->id($user),
            'module' => $module,
            'event' => $event,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'user_id' => $user?->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
