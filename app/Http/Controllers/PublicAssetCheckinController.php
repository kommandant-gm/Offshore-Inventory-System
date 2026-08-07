<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\Asset;
use App\Notifications\SupervisorWorkflowNotification;
use App\Services\SupervisorNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicAssetCheckinController extends Controller
{
    public function show(string $token): View
    {
        $assignment = $this->resolve($token)->load('asset');
        return view('it-assets.checkin-sign', compact('assignment', 'token'));
    }

    public function sign(Request $request, string $token, SupervisorNotificationService $notifications)
    {
        $data = $request->validate(['signature' => ['required', 'string', 'max:200000'], 'acknowledgment' => ['accepted']]);
        $assignment = DB::transaction(function () use ($token, $data, $request) {
            $assignment = $this->resolve($token, true);
            $assignment->update([
                'checkin_status' => 'signed', 'checkin_token' => null, 'checkin_signature' => $data['signature'],
                'checkin_signed_at' => now(), 'checkin_signed_ip' => $request->ip(), 'checkin_signed_user_agent' => $request->userAgent(),
                'returned_at' => now()->toDateString(), 'checkin_received_by_email' => 'muhd.isa@desb.net',
            ]);
            $assignment->asset()->update(['current_status' => 'available']);
            return $assignment->load('asset');
        });

        $notifications->send(new SupervisorWorkflowNotification(
            subject: "Asset check-in acknowledged: {$assignment->asset->asset_tag_no}",
            intro: "The IT Team acknowledged receipt of {$assignment->asset->asset_tag_no} from {$assignment->assigned_to_name}.",
            details: ['Asset tag' => $assignment->asset->asset_tag_no, 'Received by' => 'muhd.isa@desb.net', 'Acknowledged at' => $assignment->checkin_signed_at->format('Y-m-d H:i')],
            url: route('it-assets.show', $assignment->asset), actionLabel: 'View asset',
        ), 'Unable to send signed asset check-in supervisor notification.');

        return $this->downloadCheckinPdf($assignment);
    }

    public function testPreview(): View
    {
        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member', 'employee_id' => 'TEST-001', 'department' => 'IT', 'assigned_at' => now()->toDateString(),
        ]);
        $assignment->setRelation('asset', $asset);
        return view('it-assets.checkin-sign', ['assignment' => $assignment, 'token' => null, 'preview' => true]);
    }

    public function testSign(Request $request)
    {
        $data = $request->validate(['signature' => ['required', 'string', 'max:200000'], 'acknowledgment' => ['accepted']]);
        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member',
            'employee_id' => 'TEST-001',
            'assigned_at' => now()->toDateString(),
            'checkin_signature' => $data['signature'],
            'checkin_signed_at' => now(),
            'checkin_received_by_email' => 'muhd.isa@desb.net',
        ]);
        $assignment->setRelation('asset', $asset);

        return $this->downloadCheckinPdf($assignment);
    }

    public function previewPdf(Request $request)
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member',
            'employee_id' => 'TEST-001',
            'assigned_at' => now()->toDateString(),
            'checkin_received_by_email' => 'muhd.isa@desb.net',
        ]);
        $assignment->setRelation('asset', $asset);

        return $this->downloadCheckinPdf($assignment, 'asset-checkin-preview-'.$asset->asset_tag_no.'.pdf');
    }

    private function resolve(string $token, bool $lock = false): AssetAssignment
    {
        $query = AssetAssignment::query()->where('checkin_token', $token)->where('checkin_status', 'pending');
        $assignment = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $assignment) abort(HttpResponse::HTTP_GONE, 'This check-in link has expired or already been used.');
        return $assignment;
    }

    private function downloadCheckinPdf(AssetAssignment $assignment, ?string $filename = null)
    {
        $pdf = Pdf::loadView('it-assets.checkin-pdf', [
            'assignment' => $assignment,
            'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png'))),
        ])->setPaper('a4');

        $downloadName = $filename ?: 'asset-checkin-'.$assignment->asset->asset_tag_no.'.pdf';
        $downloadName = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $downloadName);

        return $pdf->download($downloadName);
    }
}
