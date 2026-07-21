<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAssignmentController extends Controller
{
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:255'],
            'assigned_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $selectedUser = null;
        if (! empty($data['user_id'])) {
            $branchId = app(BranchContext::class)->id($request->user());
            $selectedUser = User::query()
                ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branch) => $branch->where('branches.id', $branchId)))
                ->find($data['user_id']);

            if (! $selectedUser) {
                throw ValidationException::withMessages(['user_id' => 'Select a user who belongs to the active branch.']);
            }
        }

        DB::transaction(function () use ($asset, $data, $request, $selectedUser) {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $current = $lockedAsset->assignments()->whereNull('returned_at')->lockForUpdate()->first();

            if (! $current && $lockedAsset->current_status !== AssetStatus::Available) {
                throw ValidationException::withMessages([
                    'asset' => 'Only an available asset can be checked out.',
                ]);
            }

            if ($current) {
                $current->update([
                    'returned_at' => now()->toDateString(),
                    'received_by' => $request->user()->id,
                ]);
            }

            $lockedAsset->assignments()->create([
                'assigned_to_name' => $selectedUser?->name ?? $data['assigned_to_name'],
                'employee_id' => $selectedUser?->username ?? ($data['employee_id'] ?? null),
                'department' => $data['department'] ?? null,
                'assigned_at' => $data['assigned_at'],
                'assigned_by' => $request->user()->id,
                'remarks' => $data['remarks'] ?? null,
            ]);
            $lockedAsset->update(['current_status' => AssetStatus::Deployed]);
        });

        return back()->with('success', 'Asset checked out successfully.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        DB::transaction(function () use ($asset, $request) {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $current = $lockedAsset->assignments()->whereNull('returned_at')->lockForUpdate()->first();

            if (! $current) {
                throw ValidationException::withMessages(['asset' => 'This asset is not currently assigned.']);
            }

            $current->update([
                'returned_at' => now()->toDateString(),
                'received_by' => $request->user()->id,
            ]);
            $lockedAsset->update(['current_status' => AssetStatus::Available]);
        });

        return back()->with('success', 'Asset checked in and is now available.');
    }
}
