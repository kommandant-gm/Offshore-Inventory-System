<?php

namespace App\Http\Controllers;

use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchContextController extends Controller
{
    private const DESTINATION_ROUTES = [
        'dashboard',
        'assistant.index',
        'assets.index',
        'asset-movements.index',
        'asset-movements.create',
        'stocktakes.index',
        'asset-ledger.index',
        'cogs.index',
        'anomalies.index',
        'it-assets.dashboard',
        'it-assets.index',
        'it-licenses.index',
        'it-people.index',
        'it-assets.repairs',
        'kemaman-inventory.dashboard',
        'kemaman-inventory.index',
    ];

    public function update(Request $request, BranchContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'destination_route' => ['nullable', 'string', Rule::in(self::DESTINATION_ROUTES)],
        ]);

        $context->set($request->user(), (int) $validated['branch_id']);

        if ($destination = $validated['destination_route'] ?? null) {
            return redirect()->route($destination)->with('success', 'Active branch changed.');
        }

        return back()->with('success', 'Active branch changed.');
    }
}
