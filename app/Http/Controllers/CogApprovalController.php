<?php

namespace App\Http\Controllers;

use App\Http\Requests\CogApprovalDecisionRequest;
use App\Models\Cog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
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

    public function approve(CogApprovalDecisionRequest $request, string $token): RedirectResponse
    {
        $cog = $this->resolvePendingCogByToken($token);

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

        return back()->with('status', 'approved');
    }

    public function reject(CogApprovalDecisionRequest $request, string $token): RedirectResponse
    {
        $cog = $this->resolvePendingCogByToken($token);

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

        return back()->with('status', 'rejected');
    }

    private function resolvePendingCogByToken(string $token): Cog
    {
        $cog = Cog::query()
            ->where('approval_token', $token)
            ->first();

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
