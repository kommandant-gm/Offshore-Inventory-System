<?php

namespace App\Http\Controllers;

use App\Http\Requests\CogApprovalDecisionRequest;
use App\Models\Cog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CogApprovalController extends Controller
{
    public function show(string $token): View
    {
        $cog = $this->resolvePendingCogByToken($token);
        $cog->load('items');

        return view('cogs.approval', [
            'cog' => $cog,
            'token' => $token,
        ]);
    }

    public function approve(CogApprovalDecisionRequest $request, string $token, AuditLogger $auditLogger): RedirectResponse
    {
        $cog = DB::transaction(function () use ($request, $token) {
            $cog = $this->resolvePendingCogByToken($token, true);
            $cog->update([
            'status' => 'approved',
            'receiver_name' => $request->validated('receiver_name'),
            'receiver_designation' => $request->validated('receiver_designation'),
            'receiver_remarks' => $request->validated('receiver_remarks'),
            'approval_token' => null,
            'approval_expires_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
            ]);

            return $cog;
        });

        $auditLogger->record(
            module: 'cogs',
            event: 'approved',
            summary: "Approved COG {$cog->cog_no}.",
            auditable: $cog,
            after: [
                'status' => $cog->status,
                'receiver_name' => $cog->receiver_name,
                'receiver_designation' => $cog->receiver_designation,
            ],
            request: $request,
        );

        return back()->with('status', 'approved');
    }

    public function reject(CogApprovalDecisionRequest $request, string $token, AuditLogger $auditLogger): RedirectResponse
    {
        $cog = DB::transaction(function () use ($request, $token) {
            $cog = $this->resolvePendingCogByToken($token, true);
            $cog->update([
            'status' => 'rejected',
            'receiver_name' => $request->validated('receiver_name'),
            'receiver_designation' => $request->validated('receiver_designation'),
            'receiver_remarks' => $request->validated('receiver_remarks'),
            'approval_token' => null,
            'approval_expires_at' => null,
            'rejected_at' => now(),
            'approved_at' => null,
            ]);

            return $cog;
        });

        $auditLogger->record(
            module: 'cogs',
            event: 'rejected',
            summary: "Rejected COG {$cog->cog_no}.",
            auditable: $cog,
            after: [
                'status' => $cog->status,
                'receiver_name' => $cog->receiver_name,
                'receiver_designation' => $cog->receiver_designation,
            ],
            request: $request,
        );

        return back()->with('status', 'rejected');
    }

    private function resolvePendingCogByToken(string $token, bool $lock = false): Cog
    {
        $query = Cog::query()->where('approval_token', $token);
        $cog = ($lock ? $query->lockForUpdate() : $query)->first();

        if (! $cog) {
            throw (new ModelNotFoundException())->setModel(Cog::class);
        }

        if ($cog->status !== 'pending_approval') {
            abort(HttpResponse::HTTP_GONE, 'This approval link has already been used.');
        }

        if ($cog->approval_expires_at && $cog->approval_expires_at->isPast()) {
            abort(HttpResponse::HTTP_GONE, 'This approval link has expired.');
        }

        return $cog;
    }
}
