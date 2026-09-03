<?php

namespace App\Http\Controllers;

use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BranchContext $branchContext): RedirectResponse
    {
        abort_unless($request->user()?->canRead('dashboard'), 403);

        return match ($branchContext->branch($request->user())?->code) {
            'MIRI' => redirect()->route('major-equipment.dashboard'),
            'KEMAMAN' => redirect()->route('kemaman-inventory.dashboard'),
            default => redirect()->route('it-assets.dashboard'),
        };
    }
}
