<?php

namespace App\Http\Controllers;

use App\Models\EmailActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $logs = EmailActivityLog::query()->latest()->paginate(25)->through(fn (EmailActivityLog $log) => $this->summary($log));

        return Inertia::render('Settings/EmailActivity/Index', ['logs' => $logs]);
    }

    public function show(Request $request, EmailActivityLog $emailActivityLog): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('Settings/EmailActivity/Show', [
            'log' => [
                ...$this->summary($emailActivityLog),
                'body' => $emailActivityLog->body ?: $this->reconstructBody($emailActivityLog),
                'reconstructed' => blank($emailActivityLog->body),
                'details' => $emailActivityLog->details ?? [],
                'action_url' => $emailActivityLog->action_url,
                'action_label' => $emailActivityLog->action_label,
                'attachment_name' => $emailActivityLog->attachment_name,
                'error' => $emailActivityLog->error,
                'created_at' => $emailActivityLog->created_at?->format('d M Y h:i:s A'),
                'sent_at' => $emailActivityLog->sent_at?->format('d M Y h:i:s A'),
            ],
        ]);
    }

    private function summary(EmailActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'time' => $log->created_at?->format('d M Y h:i A'),
            'recipient' => $log->recipient,
            'subject' => $log->subject,
            'type' => $log->notification_type,
            'status' => $log->status,
        ];
    }

    private function reconstructBody(EmailActivityLog $log): string
    {
        $subject = (string) $log->subject;
        $lines = [
            '[Reconstructed content — the original body was not captured at send time.]',
            'Recipient: '.$log->recipient,
            'Subject: '.$subject,
        ];

        if (preg_match('/(?:checkout|check-in|registered|repair|allocated|requested):\s*(\S+)/i', $subject, $match)) {
            $assetTag = rtrim($match[1], '.,');
            $asset = Asset::query()->where('asset_tag_no', $assetTag)->first();
            $assignment = AssetAssignment::query()->with('asset')->whereHas('asset', fn ($query) => $query->where('asset_tag_no', $assetTag))->latest('id')->first();

            if (str_contains(strtolower($subject), 'checkout signed')) {
                $lines[] = ($assignment?->assigned_to_name ?: 'The staff member').' digitally signed an IT asset checkout form.';
                $lines[] = 'Asset tag: '.$assetTag;
                $lines[] = 'Assigned to: '.($assignment?->assigned_to_name ?: '-');
                $lines[] = 'Signed at: '.($assignment?->signed_at?->format('Y-m-d H:i') ?: '-');
            } elseif (str_contains(strtolower($subject), 'checkout') && str_contains(strtolower($subject), 'requested')) {
                $lines[] = 'An IT asset checkout form was created and is awaiting staff signature.';
                $lines[] = 'Asset tag: '.$assetTag;
                $lines[] = 'Assigned to: '.($assignment?->assigned_to_name ?: '-');
                $lines[] = 'Employee ID: '.($assignment?->employee_id ?: '-');
                $lines[] = 'Department: '.($assignment?->department ?: '-');
                $lines[] = 'Assigned date: '.($assignment?->assigned_at?->format('Y-m-d') ?: '-');
            } elseif (str_contains(strtolower($subject), 'check-in') && str_contains(strtolower($subject), 'requested')) {
                $lines[] = 'An IT asset check-in request was sent to the IT Team for acknowledgment.';
                $lines[] = 'Asset tag: '.$assetTag;
                $lines[] = 'Previously assigned to: '.($assignment?->assigned_to_name ?: '-');
                $lines[] = 'Employee ID: '.($assignment?->employee_id ?: '-');
                $lines[] = 'Department: '.($assignment?->department ?: '-');
            } elseif (str_contains(strtolower($subject), 'check-in acknowledged')) {
                $lines[] = 'The IT Team acknowledged receipt of '.$assetTag.'.';
                $lines[] = 'Received by: '.($assignment?->checkin_received_by_email ?: 'muhd.isa@desb.net');
                $lines[] = 'Acknowledged at: '.($assignment?->checkin_signed_at?->format('Y-m-d H:i') ?: '-');
            } elseif (str_contains(strtolower($subject), 'registered') && $asset) {
                $lines[] = 'Asset tag: '.$asset->asset_tag_no;
                $lines[] = 'Description: '.($asset->description ?: '-');
                $lines[] = 'Model: '.($asset->model ?: '-');
                $lines[] = 'Serial number: '.($asset->serial_no ?: '-');
            } else {
                $lines[] = 'Asset tag: '.$assetTag;
                $lines[] = 'This message was reconstructed from the saved notification subject and available asset records.';
            }
        } else {
            $lines[] = 'This message was reconstructed from the saved subject because the original message body was not stored.';
        }

        return implode("\n\n", $lines);
    }
}
