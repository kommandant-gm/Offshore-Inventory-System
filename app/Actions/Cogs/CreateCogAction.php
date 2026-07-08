<?php

namespace App\Actions\Cogs;

use App\Mail\CogApprovalMail;
use App\Models\Cog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateCogAction
{
    private const APPROVAL_TOKEN_TTL_DAYS = 7;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function execute(array $validated, ?int $userId = null): Cog
    {
        $cog = DB::transaction(fn () => $this->persist($validated, $userId));

        $this->dispatchApprovalEmail($cog);

        return $cog;
    }

    public function persist(array $validated, ?int $userId = null): Cog
    {
        $shouldSendApproval = filled($validated['receiver_email'] ?? null);

        $cog = Cog::create([
            ...Arr::except($validated, ['items']),
            'cog_no' => $this->draftNumber(),
            'status' => $shouldSendApproval ? 'pending_approval' : 'draft',
            'approval_token' => $shouldSendApproval ? Str::uuid()->toString() : null,
            'approval_sent_at' => $shouldSendApproval ? now() : null,
            'approval_expires_at' => $shouldSendApproval ? now()->addDays(self::APPROVAL_TOKEN_TTL_DAYS) : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach ($validated['items'] as $item) {
            $cog->items()->create($item);
        }

        $this->auditLogger->record(
            module: 'cogs',
            event: 'created',
            summary: "Created COG {$cog->cog_no}.",
            auditable: $cog,
            after: [
                'status' => $cog->status,
                'receiver_email' => $cog->receiver_email,
                'items_count' => count($validated['items']),
            ],
            user: $userId ? User::query()->find($userId) : null,
        );

        return $cog->load('items');
    }

    public function dispatchApprovalEmail(Cog $cog): void
    {
        if (! $cog->receiver_email || ! $cog->approval_token) {
            return;
        }

        $approvalUrl = route('cogs.approval.show', $cog->approval_token);
        $mail = Mail::to($cog->receiver_email);

        if (! empty($cog->cc_emails)) {
            $mail->cc($cog->cc_emails);
        }

        $mail->send(new CogApprovalMail($cog, $approvalUrl));
    }

    public function draftNumber(): string
    {
        $year = now()->format('y');
        $prefix = "IT/{$year}/";
        $latest = Cog::query()
            ->where('cog_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('cog_no');

        $next = 1;

        if ($latest) {
            $lastSegment = (int) str($latest)->afterLast('/')->value();
            $next = $lastSegment + 1;
        }

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
