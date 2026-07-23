<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItLicenseRequest;
use App\Http\Requests\UpdateItLicenseRequest;
use App\Models\ItLicense;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItLicenseController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,expiring_soon,expired,inactive'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $query = ItLicense::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('license_code', 'like', "%{$search}%")
                        ->orWhere('software_name', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('assigned_to', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('license_type', $type))
            ->when($filters['department'] ?? null, fn (Builder $query, string $department) => $query->where('department', $department));

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        $licenses = $query
            ->orderBy('software_name')
            ->orderBy('license_code')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ItLicense $license) => $this->summaryPayload($license));

        $all = ItLicense::query();
        $summary = [
            'total' => (clone $all)->count(),
            'active' => $this->statusQuery(clone $all, 'active')->count(),
            'expiring_soon' => $this->statusQuery(clone $all, 'expiring_soon')->count(),
            'expired' => $this->statusQuery(clone $all, 'expired')->count(),
            'users_assigned' => (int) (clone $all)->where('active', true)->sum('seats_assigned'),
        ];
        $assignmentChart = ItLicense::query()
            ->where('active', true)
            ->select('software_name')
            ->selectRaw('SUM(seats_assigned) as users_assigned')
            ->selectRaw('SUM(seats_total) as total_licenses')
            ->groupBy('software_name')
            ->orderByDesc('users_assigned')
            ->orderBy('software_name')
            ->get()
            ->map(fn (ItLicense $license) => [
                'software_name' => $license->software_name,
                'users_assigned' => (int) $license->users_assigned,
                'total_licenses' => (int) $license->total_licenses,
                'unassigned' => max(0, (int) $license->total_licenses - (int) $license->users_assigned),
            ]);

        return Inertia::render('ItLicenses/Index', [
            'licenses' => $licenses,
            'summary' => $summary,
            'assignmentChart' => $assignmentChart,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
                'department' => $filters['department'] ?? '',
            ],
            'types' => $this->typeOptions(),
            'departments' => ItLicense::query()->whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        return Inertia::render('ItLicenses/Create', [
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(StoreItLicenseRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $license = ItLicense::create($request->validated());
        $auditLogger->record(
            module: 'it_assets',
            event: 'it_license_created',
            summary: "Registered IT licence {$license->license_code} ({$license->software_name}).",
            auditable: $license,
            user: $request->user(),
            request: $request,
        );

        return redirect()->route('it-licenses.show', $license)->with('success', 'IT licence registered.');
    }

    public function show(Request $request, ItLicense $itLicense): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        return Inertia::render('ItLicenses/Show', [
            'license' => [
                ...$this->summaryPayload($itLicense),
                'license_key_masked' => $this->maskedKey($itLicense->license_key),
                'assigned_to' => $itLicense->assigned_to,
                'department' => $itLicense->department,
                'purchase_date' => $itLicense->purchase_date?->format('Y-m-d'),
                'auto_renew' => $itLicense->auto_renew,
                'renewal_cost' => $itLicense->renewal_cost,
                'supplier' => $itLicense->supplier,
                'purchase_reference' => $itLicense->purchase_reference,
                'active' => $itLicense->active,
                'remarks' => $itLicense->remarks,
            ],
        ]);
    }

    public function edit(Request $request, ItLicense $itLicense): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        return Inertia::render('ItLicenses/Edit', [
            'license' => [
                'id' => $itLicense->id,
                'license_code' => $itLicense->license_code,
                'software_name' => $itLicense->software_name,
                'vendor' => $itLicense->vendor,
                'license_type' => $itLicense->license_type,
                'license_key' => $itLicense->license_key,
                'seats_total' => $itLicense->seats_total,
                'seats_assigned' => $itLicense->seats_assigned,
                'assigned_to' => $itLicense->assigned_to,
                'department' => $itLicense->department,
                'purchase_date' => $itLicense->purchase_date?->format('Y-m-d'),
                'expiry_date' => $itLicense->expiry_date?->format('Y-m-d'),
                'auto_renew' => $itLicense->auto_renew,
                'renewal_cost' => $itLicense->renewal_cost,
                'supplier' => $itLicense->supplier,
                'purchase_reference' => $itLicense->purchase_reference,
                'active' => $itLicense->active,
                'remarks' => $itLicense->remarks,
            ],
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateItLicenseRequest $request, ItLicense $itLicense, AuditLogger $auditLogger): RedirectResponse
    {
        $itLicense->update($request->validated());
        $auditLogger->record(
            module: 'it_assets',
            event: 'it_license_updated',
            summary: "Updated IT licence {$itLicense->license_code} ({$itLicense->software_name}).",
            auditable: $itLicense,
            user: $request->user(),
            request: $request,
        );

        return redirect()->route('it-licenses.show', $itLicense)->with('success', 'IT licence updated.');
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status) {
            $this->statusQuery($query, $status);
        }
    }

    private function statusQuery(Builder $query, string $status): Builder
    {
        return match ($status) {
            'inactive' => $query->where('active', false),
            'expired' => $query->where('active', true)->whereDate('expiry_date', '<', today()),
            'expiring_soon' => $query->where('active', true)->whereBetween('expiry_date', [today(), today()->addDays(30)]),
            default => $query->where('active', true)->where(function (Builder $query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>', today()->addDays(30));
            }),
        };
    }

    private function summaryPayload(ItLicense $license): array
    {
        return [
            'id' => $license->id,
            'license_code' => $license->license_code,
            'software_name' => $license->software_name,
            'vendor' => $license->vendor,
            'license_type' => $license->license_type,
            'seats_total' => $license->seats_total,
            'seats_assigned' => $license->seats_assigned,
            'seats_available' => max(0, $license->seats_total - $license->seats_assigned),
            'assigned_to' => $license->assigned_to,
            'expiry_date' => $license->expiry_date?->format('Y-m-d'),
            'status' => $license->status(),
        ];
    }

    private function typeOptions(): array
    {
        return collect(['subscription', 'perpetual', 'volume', 'oem', 'trial'])
            ->map(fn (string $type) => ['value' => $type, 'label' => str($type)->replace('_', ' ')->title()->toString()])
            ->all();
    }

    private function maskedKey(?string $key): ?string
    {
        if (blank($key)) {
            return null;
        }

        return str_repeat('•', max(8, mb_strlen($key) - 4)).mb_substr($key, -4);
    }
}
