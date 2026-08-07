<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Mail\AssetCheckoutSignatureMail;
use App\Mail\AssetCheckinSignatureMail;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\SupervisorWorkflowNotification;
use App\Services\BranchContext;
use App\Services\SupervisorNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetAssignmentController extends Controller
{
    public function store(Request $request, Asset $asset, SupervisorNotificationService $supervisorNotifications): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'assigned_email' => ['nullable', 'email', 'max:255'],
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

        $data['assigned_email'] ??= $selectedUser?->email;
        if (! $data['assigned_email']) {
            throw ValidationException::withMessages(['assigned_email' => 'A staff email is required for digital signature.']);
        }

        $assignment = DB::transaction(function () use ($asset, $data, $request, $selectedUser) {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $current = $lockedAsset->assignments()->whereNull('returned_at')->lockForUpdate()->first();

            if (! $current && $lockedAsset->current_status !== AssetStatus::Available) {
                throw ValidationException::withMessages([
                    'asset' => 'Only an available asset can be checked out.',
                ]);
            }

            if ($current) {
                if ($current->checkin_status === 'pending') {
                    throw ValidationException::withMessages(['asset' => 'This asset is awaiting IT Team check-in acknowledgment.']);
                }
                $current->update([
                    'returned_at' => now()->toDateString(),
                    'received_by' => $request->user()->id,
                ]);
            }

            $lockedAsset->assignments()->create([
                'assigned_to_name' => $selectedUser?->name ?? $data['assigned_to_name'],
                'assigned_email' => $data['assigned_email'],
                'employee_id' => $selectedUser?->username ?? ($data['employee_id'] ?? null),
                'department' => $data['department'] ?? null,
                'assigned_at' => $data['assigned_at'],
                'assigned_by' => $request->user()->id,
                'remarks' => $data['remarks'] ?? null,
                'checkout_status' => 'pending',
                'checkout_token' => Str::random(64),
                'checkout_sent_at' => now(),
            ]);
            $lockedAsset->update(['current_status' => AssetStatus::PendingCheckout]);
            return $lockedAsset->assignments()->whereNull('returned_at')->latest('id')->first();
        });

        $assignment->load('asset');
        Mail::to($assignment->assigned_email)->send(new AssetCheckoutSignatureMail($assignment, route('public.asset-checkout.show', $assignment->checkout_token)));
        $supervisorNotifications->send(new SupervisorWorkflowNotification(
            subject: "IT asset checkout signature requested: {$asset->asset_tag_no}",
            intro: "{$request->user()->name} created an IT asset checkout form awaiting staff signature.",
            details: [
                'Asset tag' => $asset->asset_tag_no,
                'Assigned to' => $assignment?->assigned_to_name ?: '-',
                'Employee ID' => $assignment?->employee_id ?: '-',
                'Department' => $assignment?->department ?: '-',
                'Assigned date' => $assignment?->assigned_at?->format('Y-m-d') ?: '-',
            ],
            url: route('it-assets.show', $asset),
            actionLabel: 'View asset',
        ), 'Unable to send IT asset checkout supervisor notification.');

        return back()->with('success', 'Checkout form sent to the staff member for digital signature.');
    }

    public function destroy(Request $request, Asset $asset, SupervisorNotificationService $supervisorNotifications): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $asset->load('currentAssignment');
        $previousAssignment = $asset->currentAssignment;

        DB::transaction(function () use ($asset, $request) {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $current = $lockedAsset->assignments()->whereNull('returned_at')->lockForUpdate()->first();

            if (! $current) {
                throw ValidationException::withMessages(['asset' => 'This asset is not currently assigned.']);
            }

            if ($current->checkout_status !== 'signed') {
                throw ValidationException::withMessages(['asset' => 'The checkout must be signed before the asset can be checked in.']);
            }
            if ($current->checkin_status === 'pending') {
                throw ValidationException::withMessages(['asset' => 'A check-in form has already been sent to the IT Team.']);
            }
            $current->update(['checkin_status' => 'pending', 'checkin_token' => Str::random(64), 'checkin_sent_at' => now()]);
            $lockedAsset->update(['current_status' => AssetStatus::PendingCheckin]);
        });

        $asset->load('currentAssignment');
        $assignment = $asset->currentAssignment;
        Mail::to('muhd.isa@desb.net')->send(new AssetCheckinSignatureMail($assignment, route('public.asset-checkin.show', $assignment->checkin_token)));
        $supervisorNotifications->send(new SupervisorWorkflowNotification(
            subject: "IT asset check-in requested: {$asset->asset_tag_no}",
            intro: "{$request->user()->name} sent an IT asset check-in request to the IT Team for acknowledgment.",
            details: [
                'Asset tag' => $asset->asset_tag_no,
                'Previously assigned to' => $previousAssignment?->assigned_to_name ?: '-',
                'Employee ID' => $previousAssignment?->employee_id ?: '-',
                'Department' => $previousAssignment?->department ?: '-',
                'Requested at' => now()->toDateString(),
                'Technician' => 'muhd.isa@desb.net',
            ],
            url: route('it-assets.show', $asset),
            actionLabel: 'View asset',
        ), 'Unable to send IT asset check-in supervisor notification.');

        return back()->with('success', 'Check-in form sent to the IT Team for acknowledgment.');
    }
}
