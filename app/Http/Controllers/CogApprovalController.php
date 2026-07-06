<?php

namespace App\Http\Controllers;

use App\Http\Requests\CogApprovalDecisionRequest;
use App\Models\Cog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CogApprovalController extends Controller
{
    public function show(string $token): View
    {
        $cog = Cog::query()->with('items')->where('approval_token', $token)->firstOrFail();

        return view('cogs.approval', [
            'cog' => $cog,
            'token' => $token,
        ]);
    }

    public function approve(CogApprovalDecisionRequest $request, string $token): RedirectResponse
    {
        $cog = Cog::query()->where('approval_token', $token)->firstOrFail();

        $cog->update([
            'status' => 'approved',
            'receiver_name' => $request->validated('receiver_name'),
            'receiver_designation' => $request->validated('receiver_designation'),
            'receiver_remarks' => $request->validated('receiver_remarks'),
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return back()->with('status', 'approved');
    }

    public function reject(CogApprovalDecisionRequest $request, string $token): RedirectResponse
    {
        $cog = Cog::query()->where('approval_token', $token)->firstOrFail();

        $cog->update([
            'status' => 'rejected',
            'receiver_name' => $request->validated('receiver_name'),
            'receiver_designation' => $request->validated('receiver_designation'),
            'receiver_remarks' => $request->validated('receiver_remarks'),
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        return back()->with('status', 'rejected');
    }
}
