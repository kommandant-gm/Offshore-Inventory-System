<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Http\RedirectResponse;
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
            'users' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'viewer',
                    'permissions' => $user->resolvedPermissions(),
                ]),
        ]);
    }

    public function updateUserAccess(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'role' => $request->string('role')->value(),
            'permissions' => AccessMatrix::normalizePermissions(
                $request->input('permissions', []),
                $request->string('role')->value()
            ),
        ]);

        return back()->with('success', "Access updated for {$user->name}.");
    }
}
