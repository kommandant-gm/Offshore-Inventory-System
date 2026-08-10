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
use App\Models\IssueLog;
use App\Models\EmailActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Mail\AssetCheckoutSignatureMail;
use App\Mail\AssetCheckinSignatureMail;
use App\Services\AuditLogger;
use App\Support\AccessMatrix;
use App\Services\LdapAuthenticator;
use App\Notifications\SupervisorWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

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
            'canEditSettings' => true,
            'supervisorEmails' => User::query()->where('role', 'supervisor')->where('directory_active', true)->whereNotNull('email')->pluck('email')->all() ?: config('mail.supervisor_addresses', []),
            'emailActivity' => [
                'total' => EmailActivityLog::count(),
                'sent' => EmailActivityLog::where('status', 'sent')->count(),
                'pending' => EmailActivityLog::where('status', 'pending')->count(),
                'failed' => EmailActivityLog::where('status', 'failed')->count(),
                'recent' => EmailActivityLog::latest()->take(10)->get()->map(fn (EmailActivityLog $log) => [
                    'id' => $log->id, 'time' => $log->created_at?->format('d M Y h:i A'), 'recipient' => $log->recipient,
                    'subject' => $log->subject, 'type' => $log->notification_type, 'status' => $log->status,
                    'error' => $log->error, 'url' => route('settings.email-activity.show', $log),
                ]),
                'full_url' => route('settings.email-activity.index'),
            ],
            'issueSummary' => [
                'total' => IssueLog::count(),
                'errors' => IssueLog::where('level', 'error')->count(),
                'warnings' => IssueLog::where('level', 'warning')->count(),
            ],
            'recentIssues' => IssueLog::query()->latest()->take(5)->get()->map(fn (IssueLog $log) => IssueLogController::format($log)),
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
            'users' => User::query()->where('directory_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'department' => $user->department,
                    'job_title' => $user->job_title,
                    'role' => in_array($user->role, ['viewer', null, ''], true) ? 'none' : $user->role,
                    'permissions' => $user->resolvedPermissions(),
                    'branch_access' => $user->branches->mapWithKeys(fn ($branch) => [(string) $branch->id => $branch->pivot->access_level]),
                    'default_branch_id' => $user->branches->first(fn ($branch) => (bool) $branch->pivot->is_default)?->id,
                ]),
        ]);
    }

    public function sendSupervisorTestEmail(): RedirectResponse
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);
        $recipients = User::query()->where('role', 'supervisor')->where('directory_active', true)->whereNotNull('email')->pluck('email')->all() ?: config('mail.supervisor_addresses', []);

        if (empty($recipients)) {
            return back()->with('error', 'No supervisor email recipients are configured.');
        }

        try {
            $notification = new SupervisorWorkflowNotification(
                subject: 'Test email - Dayang Inventory Management System',
                intro: request()->user()->name.' sent a test email from the Settings page.',
                details: ['Recipients' => implode(', ', $recipients), 'Sent at' => now()->format('Y-m-d H:i:s')],
                url: null,
                actionLabel: '',
            );
            foreach ($recipients as $address) {
                Notification::route('mail', $address)->notify($notification);
            }
        } catch (\Throwable $exception) {
            Log::error('Unable to send supervisor test email.', ['exception' => $exception]);
            return back()->with('error', 'Test email could not be sent. Check the mail settings and application log.');
        }

        return back()->with('success', 'Test email sent to '.implode(', ', $recipients).'.');
    }

    public function importLdapUsers(LdapAuthenticator $ldap): RedirectResponse
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);
        @set_time_limit(120);
        try {
            $result = $ldap->importAllUsers();
        } catch (\Throwable $exception) {
            Log::error('LDAP user import failed unexpectedly.', ['exception' => $exception]);
            return back()->with('ldap_import_error', 'LDAP import failed unexpectedly. Check the application log and confirm all database migrations have been run.');
        }

        if (! $result['ok']) {
            return back()->with('ldap_import_error', $result['error']);
        }

        return back()->with('ldap_import_success', "LDAP import completed: {$result['synced']} users synced ({$result['created']} new, {$result['updated']} updated).");
    }

    public function sendAssetCheckoutTestEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $asset = Asset::query()->first();

        if (! $asset) {
            return back()->with('checkout_error', 'Create at least one IT asset before sending the checkout form test.');
        }

        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member', 'assigned_email' => $data['email'],
            'employee_id' => 'TEST-001', 'department' => 'IT', 'assigned_at' => now()->toDateString(),
        ]);
        $assignment->setRelation('asset', $asset);

        try {
            Mail::to($data['email'])->send(new AssetCheckoutSignatureMail($assignment, route('settings.asset-checkout-test.preview', ['email' => $data['email']]), true));
        } catch (\Throwable $exception) {
            Log::error('Unable to send asset checkout test email.', ['exception' => $exception]);
            return back()->with('checkout_error', 'Asset checkout test email could not be sent. Check the mail settings.');
        }

        return back()->with('checkout_success', 'Asset checkout test email sent to '.$data['email'].'.');
    }

    public function sendAssetCheckinTestEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $asset = Asset::query()->first();
        if (! $asset) return back()->with('checkin_error', 'Create at least one IT asset before sending the check-in form test.');

        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member', 'employee_id' => 'TEST-001', 'department' => 'IT', 'assigned_at' => now()->toDateString(),
        ]);
        $assignment->setRelation('asset', $asset);

        try {
            Mail::to($data['email'])->send(new AssetCheckinSignatureMail($assignment, route('settings.asset-checkin-test.preview')));
        } catch (\Throwable $exception) {
            Log::error('Unable to send asset check-in test email.', ['exception' => $exception]);
            return back()->with('checkin_error', 'Asset check-in test email could not be sent. Check the mail settings.');
        }
        return back()->with('checkin_success', 'Asset check-in test email sent to '.$data['email'].'.');
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
                'permissions' => AccessMatrix::permissionsForRole($newRole),
            ]);

            if ($request->has('branch_access')) {
                $branchAccess = collect($request->validated('branch_access'))->reject(fn ($level) => $level === 'none');
                $defaultBranchId = (int) $request->validated('default_branch_id');
                if ($branchAccess->isNotEmpty() && ($branchAccess->has((string) $defaultBranchId) || $branchAccess->has($defaultBranchId))) {
                    $target->branches()->sync($branchAccess->mapWithKeys(fn ($level, $branchId) => [(int) $branchId => [
                        'access_level' => $level, 'is_default' => (int) $branchId === $defaultBranchId,
                    ]])->all());
                }
            }

            if ($newRole === 'miri') {
                $miriBranchId = DB::table('branches')->where('code', 'MIRI')->value('id');

                if ($miriBranchId) {
                    $target->branches()->sync([
                        $miriBranchId => ['access_level' => 'edit'],
                    ]);
                }
            }

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
