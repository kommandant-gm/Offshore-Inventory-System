<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\Asset;
use App\Mail\AssetCheckoutSignatureMail;
use App\Notifications\SupervisorWorkflowNotification;
use App\Services\SupervisorNotificationService;
use App\Support\AssetCheckoutPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
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
        return view('it-assets.checkout-sign', ['assignment' => $assignment, 'token' => $token, 'policyItems' => AssetCheckoutPolicy::items(), 'policyUrl' => AssetCheckoutPolicy::ICT_POLICY_URL]);
    }

    public function sign(Request $request, string $token, SupervisorNotificationService $notifications)
    {
        $data = $this->validateSignature($request);
        $assignment = DB::transaction(function () use ($token, $data, $request) {
            $assignment = $this->resolve($token, true);
            $assignment->update(['checkout_status' => 'signed', 'checkout_token' => null, 'signature' => $data['signature'], 'policy_acknowledgments' => array_values($data['acknowledgments']), 'policy_acknowledged_at' => now(), 'signed_at' => now(), 'signed_ip' => $request->ip(), 'signed_user_agent' => $request->userAgent()]);
            $assignment->asset()->update(['current_status' => 'deployed']);
            return $assignment->load('asset');
        });

        $notifications->send(new SupervisorWorkflowNotification(
            subject: "Asset checkout signed: {$assignment->asset->asset_tag_no}",
            intro: "{$assignment->assigned_to_name} digitally signed an IT asset checkout form.",
            details: ['Asset tag' => $assignment->asset->asset_tag_no, 'Assigned to' => $assignment->assigned_to_name, 'Signed at' => $assignment->signed_at->format('Y-m-d H:i')],
            url: route('it-assets.show', $assignment->asset), actionLabel: 'View asset',
        ), 'Unable to send signed asset checkout supervisor notification.');

        $pdf = Pdf::loadView('it-assets.checkout-pdf', [
            'assignment' => $assignment,
            'policyItems' => AssetCheckoutPolicy::items(),
            'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png'))),
        ])->setPaper('a4');

        return $pdf->download('asset-checkout-'.$assignment->asset->asset_tag_no.'.pdf');
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
        return view('it-assets.checkout-sign', ['assignment' => $assignment, 'token' => null, 'preview' => true, 'policyItems' => AssetCheckoutPolicy::items(), 'policyUrl' => AssetCheckoutPolicy::ICT_POLICY_URL]);
    }

    public function testSign(Request $request): RedirectResponse
    {
        $this->validateSignature($request);
        return redirect()->route('public.asset-checkout.complete')->with('status', 'test');
    }

    private function resolve(string $token, bool $lock = false): AssetAssignment
    {
        $query = AssetAssignment::query()->where('checkout_token', $token)->where('checkout_status', 'pending');
        $assignment = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $assignment) abort(HttpResponse::HTTP_GONE, 'This checkout link has expired or already been used.');
        return $assignment;
    }

    private function validateSignature(Request $request): array
    {
        $expected = array_keys(AssetCheckoutPolicy::items());
        $data = $request->validate([
            'signature' => ['required', 'string', 'max:200000'],
            'acknowledgments' => ['required', 'array', 'size:'.count($expected)],
            'acknowledgments.*' => ['required', 'string', 'in:'.implode(',', $expected)],
        ]);

        if (count(array_unique($data['acknowledgments'])) !== count($expected) || array_diff($expected, $data['acknowledgments'])) {
            abort(422, 'All asset checkout policy acknowledgments are required.');
        }

        return $data;
    }
}
