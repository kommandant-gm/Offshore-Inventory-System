<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditLogger;
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

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Asset::withoutGlobalScopes()->where('public_token', $token)->exists());

        return $token;
    }
}
