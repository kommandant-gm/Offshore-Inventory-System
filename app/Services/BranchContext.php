<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class BranchContext
{
    // Request attributes never survive into another HTTP request.
    private function remember(string $key, callable $resolve): mixed
    {
        $cache = request()->attributes->get('_branch_context_cache', []);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $resolve();
            request()->attributes->set('_branch_context_cache', $cache);
        }
        return $cache[$key];
    }

    public function forget(): void
    {
        request()->attributes->remove('_branch_context_cache');
    }

    private function memberships(User $user)
    {
        return $this->remember('memberships:'.$user->id, fn () => $user->branches()->get());
    }

    public function accessibleIds(?User $user = null): array
    {
        $user ??= auth()->user();
        return $this->remember('accessible:'.$user?->id.':'.$user?->role.':'.$user?->username.':'.$user?->department, function () use ($user) {
            if ($user?->role === 'miri' || $user?->isMiriRestrictedUser()) {
                return Branch::query()->where('code', 'MIRI')->where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            }
            if ($user?->isSuperAdmin() || $user?->isItDigitalUser() || in_array($user?->role, ['admin', 'supervisor', 'technician'], true)) {
                return Branch::query()->where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            }
            return $user ? $this->memberships($user)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        });
    }

    public function id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        if (! $user) return null;

        $accessible = $this->accessibleIds($user);
        $sessionId = (int) session('branch_id', 0);
        if ($sessionId && in_array($sessionId, $accessible, true)) return $sessionId;

        $defaultBranchId = $this->memberships($user)->first(fn ($branch) => $branch->pivot->is_default && in_array((int) $branch->id, $accessible, true))?->id;

        return $defaultBranchId ?? ($accessible[0] ?? null);
    }

    public function branch(?User $user = null): ?Branch
    {
        $id = $this->id($user);
        return $id ? $this->remember('branch:'.$id, fn () => Branch::find($id)) : null;
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
        $level = $this->memberships($user)->firstWhere('id', $branchId)?->pivot?->access_level;
        return in_array($level, ['edit', 'manage'], true);
    }
}
