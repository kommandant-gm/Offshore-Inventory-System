<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function update(Request $request, BranchContext $context): RedirectResponse
    {
        $validated = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        $context->set($request->user(), (int) $validated['branch_id']);
        return back()->with('success', 'Active branch changed.');
    }
}
