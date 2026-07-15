<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class BranchContext
{
    public function accessibleIds(?User $user = null): array
    {
        $user ??= auth()->user();
        return $user?->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all() ?? [];
    }

    public function id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        if (! $user) return null;

        $accessible = $this->accessibleIds($user);
        $sessionId = (int) session('branch_id', 0);
        if ($sessionId && in_array($sessionId, $accessible, true)) return $sessionId;

        return $user->branches()->wherePivot('is_default', true)->value('branches.id')
            ?? ($accessible[0] ?? null);
    }

    public function branch(?User $user = null): ?Branch
    {
        $id = $this->id($user);
        return $id ? Branch::find($id) : null;
    }

    public function set(User $user, int $branchId): void
    {
        if (! in_array($branchId, $this->accessibleIds($user), true)) {
            throw new AuthorizationException('You do not have access to that branch.');
        }
        session(['branch_id' => $branchId]);
    }

    public function canEdit(User $user, ?int $branchId): bool
    {
        if (! $branchId) return false;
        $level = $user->branches()->whereKey($branchId)->first()?->pivot?->access_level;
        return in_array($level, ['edit', 'manage'], true);
    }
}
