<?php

namespace App\Jobs;

use App\Mail\AssetCheckinSignatureMail;
use App\Mail\AssetCheckoutSignatureMail;
use App\Models\AssetAssignment;
use App\Models\EmailActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAssetSignatureEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $assignmentId, public int $branchId, public int $logId, public bool $checkin)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $log = EmailActivityLog::findOrFail($this->logId);
        if ($log->status === 'sent') return;
        $assignment = AssetAssignment::withoutGlobalScopes()->where('branch_id', $this->branchId)->findOrFail($this->assignmentId);
        $assignment->setRelation('asset', $assignment->asset()->withoutGlobalScopes()->where('branch_id', $this->branchId)->firstOrFail());
        $token = $this->checkin ? $assignment->checkin_token : $assignment->checkout_token;
        $url = route($this->checkin ? 'public.asset-checkin.show' : 'public.asset-checkout.show', $token);
        if ($log->action_url !== $url) {
            $log->update(['status' => 'failed', 'error' => 'Superseded by a newer acknowledgment link.']);
            return;
        }
        $mail = $this->checkin ? new AssetCheckinSignatureMail($assignment, $url) : new AssetCheckoutSignatureMail($assignment, $url);
        $body = $mail->render();
        Mail::to($log->recipient)->send($mail);
        $log->update(['body' => $body, 'status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    public function failed(?\Throwable $error): void
    {
        EmailActivityLog::whereKey($this->logId)->where('status', '!=', 'sent')
            ->update(['status' => 'failed', 'error' => $error?->getMessage() ?? 'Email delivery failed.']);
    }
}
