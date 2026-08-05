<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\Asset;
use App\Mail\AssetCheckoutSignatureMail;
use App\Notifications\SupervisorWorkflowNotification;
use App\Services\SupervisorNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicAssetCheckoutController extends Controller
{
    public function show(string $token): View
    {
        $assignment = $this->resolve($token)->load('asset.category', 'asset.currentLocation');
        return view('it-assets.checkout-sign', compact('assignment', 'token'));
    }

    public function sign(Request $request, string $token, SupervisorNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate(['signature' => ['required', 'string', 'max:200000']]);
        $assignment = DB::transaction(function () use ($token, $data, $request) {
            $assignment = $this->resolve($token, true);
            $assignment->update(['checkout_status' => 'signed', 'checkout_token' => null, 'signature' => $data['signature'], 'signed_at' => now(), 'signed_ip' => $request->ip(), 'signed_user_agent' => $request->userAgent()]);
            $assignment->asset()->update(['current_status' => 'deployed']);
            return $assignment->load('asset');
        });

        $notifications->send(new SupervisorWorkflowNotification(
            subject: "Asset checkout signed: {$assignment->asset->asset_tag_no}",
            intro: "{$assignment->assigned_to_name} digitally signed an IT asset checkout form.",
            details: ['Asset tag' => $assignment->asset->asset_tag_no, 'Assigned to' => $assignment->assigned_to_name, 'Signed at' => $assignment->signed_at->format('Y-m-d H:i')],
            url: route('it-assets.show', $assignment->asset), actionLabel: 'View asset',
        ), 'Unable to send signed asset checkout supervisor notification.');

        return redirect()->route('public.asset-checkout.complete');
    }

    public function complete(): View
    {
        return view('it-assets.checkout-complete');
    }

    public function testPreview(): View
    {
        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member', 'assigned_email' => request()->query('email', 'test@example.com'),
            'employee_id' => 'TEST-001', 'department' => 'IT', 'assigned_at' => now()->toDateString(),
        ]);
        $assignment->setRelation('asset', $asset);
        return view('it-assets.checkout-sign', ['assignment' => $assignment, 'token' => null, 'preview' => true]);
    }

    private function resolve(string $token, bool $lock = false): AssetAssignment
    {
        $query = AssetAssignment::query()->where('checkout_token', $token)->where('checkout_status', 'pending');
        $assignment = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $assignment) abort(HttpResponse::HTTP_GONE, 'This checkout link has expired or already been used.');
        return $assignment;
    }
}
