<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\Asset;
use App\Models\ItMovementDocument;
use App\Models\ItPersonLink;
use App\Mail\AssetCheckoutSignatureMail;
use App\Notifications\SupervisorWorkflowNotification;
use App\Services\SupervisorNotificationService;
use App\Support\AssetCheckoutPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicAssetCheckoutController extends Controller
{
    public function show(string $token): View
    {
        $assignment = $this->resolve($token)->load('asset.category', 'asset.currentLocation');
        $this->applyLinkedPerson($assignment);
        return view('it-assets.checkout-sign', ['assignment' => $assignment, 'token' => $token, 'policyItems' => AssetCheckoutPolicy::items(), 'policyUrl' => AssetCheckoutPolicy::ICT_POLICY_URL]);
    }

    public function sign(Request $request, string $token, SupervisorNotificationService $notifications)
    {
        $data = $this->validateSignature($request);
        $assignment = DB::transaction(function () use ($token, $data, $request) {
            $assignment = $this->resolve($token, true);
            $this->applyLinkedPerson($assignment);
            $assignment->update(['checkout_status' => 'signed', 'checkout_token' => null, 'signature' => $data['signature'], 'policy_acknowledgments' => array_values($data['acknowledgments']), 'policy_acknowledged_at' => now(), 'signed_at' => now(), 'signed_ip' => $request->ip(), 'signed_user_agent' => $request->userAgent()]);
            $assignment->asset()->update(['current_status' => 'deployed']);
            return $assignment->load('asset');
        });

        $document = null;
        try {
            $this->downloadCheckoutPdf($assignment, null, true, function (ItMovementDocument $movementDocument) use (&$document) {
                $document = $movementDocument;
            });
        } catch (\Throwable $exception) {
            $retryToken = Str::random(64);
            $assignment->update([
                'checkout_status' => 'pending',
                'checkout_token' => $retryToken,
                'signature' => null,
                'policy_acknowledgments' => null,
                'policy_acknowledged_at' => null,
                'signed_at' => null,
                'signed_ip' => null,
                'signed_user_agent' => null,
            ]);
            $assignment->asset()->update(['current_status' => 'pending_checkout']);
            Log::error('Unable to generate signed asset checkout PDF.', [
                'assignment_id' => $assignment->id,
                'asset_id' => $assignment->asset_id,
                'exception' => $exception,
            ]);

            return redirect()->route('public.asset-checkout.show', $retryToken)
                ->with('checkout_error', 'The signature was not completed because the PDF could not be generated. Please sign again.');
        }

        $notifications->send(new SupervisorWorkflowNotification(
            subject: "Asset checkout signed: {$assignment->asset->asset_tag_no}",
            intro: "{$assignment->assigned_to_name} digitally signed an IT asset checkout form.",
            details: ['Asset tag' => $assignment->asset->asset_tag_no, 'Assigned to' => $assignment->assigned_to_name, 'Signed at' => $assignment->signed_at->format('Y-m-d H:i')],
            url: route('it-assets.show', $assignment->asset), actionLabel: 'View asset',
            attachmentPath: $document?->path,
            attachmentName: $document?->filename,
        ), 'Unable to send signed asset checkout supervisor notification.', array_merge(
            [$assignment->assigned_email],
            $notifications->technicianRecipients(),
        ));

        return redirect()->route('public.asset-checkout.complete')->with('status', 'checkout');
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

    public function testSign(Request $request)
    {
        $data = $this->validateSignature($request);
        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member',
            'assigned_email' => request()->query('email', 'test@example.com'),
            'employee_id' => 'TEST-001',
            'department' => 'IT',
            'assigned_at' => now()->toDateString(),
            'signature' => $data['signature'],
            'policy_acknowledgments' => array_values($data['acknowledgments']),
            'signed_at' => now(),
        ]);
        $assignment->setRelation('asset', $asset);

        return $this->downloadCheckoutPdf($assignment);
    }

    public function previewPdf(Request $request)
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $asset = Asset::query()->with('category', 'currentLocation')->firstOrFail();
        $assignment = new AssetAssignment([
            'assigned_to_name' => 'Test Staff Member',
            'employee_id' => 'TEST-001',
            'department' => 'IT',
            'assigned_at' => now()->toDateString(),
            'signed_at' => now(),
        ]);
        $assignment->setRelation('asset', $asset);

        return $this->downloadCheckoutPdf($assignment, 'asset-checkout-preview-'.$asset->asset_tag_no.'.pdf');
    }

    private function resolve(string $token, bool $lock = false): AssetAssignment
    {
        $query = AssetAssignment::query()->where('checkout_token', $token)->where('checkout_status', 'pending');
        $assignment = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $assignment) abort(HttpResponse::HTTP_GONE, 'This checkout link has expired or already been used.');
        return $assignment;
    }

    private function applyLinkedPerson(AssetAssignment $assignment): void
    {
        $linkedUser = ItPersonLink::query()
            ->with('user')
            ->where('manual_identity', mb_strtolower(trim((string) $assignment->assigned_to_name)))
            ->first()?->user;

        if (! $linkedUser || ! $linkedUser->directory_active) {
            return;
        }

        $assignment->assigned_to_name = $linkedUser->name;
        $assignment->assigned_email = $linkedUser->email;
        $assignment->employee_id = $linkedUser->username;
        $assignment->department = $linkedUser->department;
        $assignment->job_title = $linkedUser->job_title;
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

    private function downloadCheckoutPdf(AssetAssignment $assignment, ?string $filename = null, bool $record = false, ?callable $onDocument = null)
    {
        $pdf = Pdf::loadView('it-assets.checkout-pdf', [
            'assignment' => $assignment,
            'policyItems' => AssetCheckoutPolicy::items(),
            'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png'))),
        ])->setPaper('a4');

        $downloadName = $filename ?: 'asset-checkout-'.$assignment->asset->asset_tag_no.'.pdf';
        $downloadName = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $downloadName);

        if ($record) {
            $path = 'asset-movement-documents/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $pdf->output());
            $document = ItMovementDocument::create([
                'asset_assignment_id' => $assignment->id,
                'document_type' => 'checkout',
                'filename' => $downloadName,
                'path' => $path,
                'generated_at' => now(),
            ]);
            if ($onDocument) {
                $onDocument($document);
            }
        }

        return $pdf->download($downloadName);
    }
}
