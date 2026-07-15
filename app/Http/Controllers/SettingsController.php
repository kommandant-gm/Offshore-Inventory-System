<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Category;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Stocktake;
use App\Models\User;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Support\AccessMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('settings'), 403);

        $latestMovement = InventoryTransaction::query()
            ->latest('transaction_date')
            ->latest('id')
            ->first();

        return Inertia::render('Settings/Index', [
            'stats' => [
                'users' => User::count(),
                'categories' => Category::count(),
                'locations' => Location::count(),
                'items' => InventoryItem::count(),
                'movements' => InventoryTransaction::count(),
                'stocktakes' => Stocktake::count(),
                'audits' => AuditLog::count(),
            ],
            'latestMovementDate' => $latestMovement?->transaction_date?->format('Y-m-d'),
            'canEditSettings' => request()->user()?->canEdit('settings') ?? false,
            'roleOptions' => collect(AccessMatrix::roleOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'permissionLevels' => collect(AccessMatrix::levelOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'permissionModules' => collect(AccessMatrix::modules())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'rolePresets' => collect(AccessMatrix::roleOptions())
                ->mapWithKeys(fn (string $label, string $value) => [$value => AccessMatrix::permissionsForRole($value)]),
            'branchOptions' => Branch::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'users' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role ?? 'viewer',
                    'permissions' => $user->resolvedPermissions(),
                    'branch_access' => $user->branches->mapWithKeys(fn ($branch) => [(string) $branch->id => $branch->pivot->access_level]),
                    'default_branch_id' => $user->branches->first(fn ($branch) => (bool) $branch->pivot->is_default)?->id,
                ]),
        ]);
    }

    public function updateUserAccess(UpdateUserAccessRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $before = [
            'role' => $user->role,
            'permissions' => $user->resolvedPermissions(),
            'branch_access' => $user->branches()->pluck('access_level', 'branches.id')->all(),
        ];

        DB::transaction(function () use ($request, $user) {
            User::query()->lockForUpdate()->get();
            $target = User::query()->findOrFail($user->id);
            $newRole = $request->string('role')->value();

            $adminCount = User::query()->where(fn ($query) => $query
                ->where('role', 'admin')
                ->orWhereNull('role')
                ->orWhere('role', ''))
                ->count();

            if ($target->isAdmin() && $newRole !== 'admin' && $adminCount <= 1) {
                throw ValidationException::withMessages(['role' => 'The last administrator cannot be demoted.']);
            }

            $target->update([
                'role' => $newRole,
                'permissions' => AccessMatrix::normalizePermissions($request->input('permissions', []), $newRole),
            ]);

            $branchAccess = collect($request->validated('branch_access'))->reject(fn ($level) => $level === 'none');
            if ($branchAccess->isEmpty()) {
                throw ValidationException::withMessages(['branch_access' => 'Every user must have access to at least one branch.']);
            }
            $defaultBranchId = (int) $request->validated('default_branch_id');
            if (! $branchAccess->has((string) $defaultBranchId) && ! $branchAccess->has($defaultBranchId)) {
                throw ValidationException::withMessages(['default_branch_id' => 'Default branch must be one of the accessible branches.']);
            }
            $target->branches()->sync($branchAccess->mapWithKeys(fn ($level, $branchId) => [(int) $branchId => [
                'access_level' => $level, 'is_default' => (int) $branchId === $defaultBranchId,
            ]])->all());

            $user->setRawAttributes($target->getAttributes(), true);
        });

        $auditLogger->record(
            module: 'settings',
            event: 'access_updated',
            summary: "Updated access for {$user->name}.",
            auditable: $user,
            before: $before,
            after: [
                'role' => $user->role,
                'permissions' => $user->resolvedPermissions(),
                'branch_access' => $user->branches()->pluck('access_level', 'branches.id')->all(),
            ],
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', "Access updated for {$user->name}.");
    }
}
