<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetQrCodeController extends Controller
{
    public function show(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $asset->load(['category:id,name', 'currentLocation:id,name']);

        return Inertia::render('ItAssets/QrCode', [
            'asset' => [
                'id' => $asset->id,
                'asset_tag_no' => $asset->asset_tag_no,
                'description' => $asset->model ?: $asset->description,
                'category' => $asset->category?->name,
                'serial_no' => $asset->serial_no,
                'location' => $asset->currentLocation?->name,
                'public_url' => $asset->public_token ? route('public.it-assets.show', $asset->public_token) : null,
            ],
        ]);
    }

    public function store(Request $request, Asset $asset, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        if (! $asset->public_token) {
            $asset->update(['public_token' => $this->uniqueToken()]);
            $auditLogger->record(
                module: 'it_assets',
                event: 'qr_code_generated',
                summary: "Generated public QR code for {$asset->asset_tag_no}.",
                auditable: $asset,
                user: $request->user(),
                request: $request,
            );
        }

        return back()->with('success', 'Asset QR code generated.');
    }

    public function update(Request $request, Asset $asset, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $asset->update(['public_token' => $this->uniqueToken()]);
        $auditLogger->record(
            module: 'it_assets',
            event: 'qr_code_regenerated',
            summary: "Regenerated public QR code for {$asset->asset_tag_no}.",
            auditable: $asset,
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Asset QR code regenerated. The previous code is no longer valid.');
    }

    public function storeAll(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:100'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:100'],
            'assignment' => ['nullable', 'in:assigned,unassigned'],
        ]);

        $generated = 0;
        $this->filteredAssets($filters)
            ->whereNull('public_token')
            ->select(['id', 'asset_tag_no', 'public_token'])
            ->chunkById(100, function ($assets) use (&$generated) {
                foreach ($assets as $asset) {
                    $asset->update(['public_token' => $this->uniqueToken()]);
                    $generated++;
                }
            });

        if ($generated > 0) {
            $auditLogger->record(
                module: 'it_assets',
                event: 'qr_codes_bulk_generated',
                summary: "Generated public QR codes for {$generated} IT assets.",
                user: $request->user(),
                request: $request,
            );
        }

        $message = $generated > 0
            ? "Generated QR codes for {$generated} ".str('asset')->plural($generated).'. Existing QR codes were unchanged.'
            : 'All matching assets already have QR codes.';

        return back()->with('success', $message);
    }

    private function filteredAssets(array $filters): Builder
    {
        return Asset::query()
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('asset_tag_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_no', 'like', "%{$search}%")
                        ->orWhereHas('currentAssignment', fn ($assignment) => $assignment
                            ->where('assigned_to_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('current_location_id', $location))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('current_status', $status))
            ->when($filters['department'] ?? null, fn ($query, $department) => $query->whereHas(
                'currentAssignment', fn ($assignment) => $assignment->where('department', $department)
            ))
            ->when($filters['assignee'] ?? null, fn ($query, $assignee) => $query->whereHas(
                'currentAssignment', fn ($assignment) => $assignment->where('assigned_to_name', $assignee)
            ))
            ->when($filters['os'] ?? null, fn ($query, $os) => $query->where('operating_system', $os))
            ->when(($filters['assignment'] ?? null) === 'assigned', fn ($query) => $query->whereHas('currentAssignment'))
            ->when(($filters['assignment'] ?? null) === 'unassigned', fn ($query) => $query->whereDoesntHave('currentAssignment'));
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Asset::withoutGlobalScopes()->where('public_token', $token)->exists());

        return $token;
    }
}
