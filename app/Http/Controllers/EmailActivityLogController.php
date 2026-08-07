<?php

namespace App\Http\Controllers;

use App\Models\EmailActivityLog;
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
                'body' => $emailActivityLog->body,
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
}
