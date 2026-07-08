<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\ImportInventoryCsvAction;
use App\Http\Requests\StoreInventoryImportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryImportController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('assets'), 403);

        return Inertia::render('Assets/Import', [
            'defaultMovementDate' => now()->toDateString(),
        ]);
    }

    public function store(
        StoreInventoryImportRequest $request,
        ImportInventoryCsvAction $importInventoryCsvAction,
    ): RedirectResponse {
        $summary = $importInventoryCsvAction->execute(
            $request->file('file'),
            $request->date('movement_date'),
            $request->user()->id,
        );

        return redirect()
            ->route('assets.index')
            ->with('success', sprintf(
                'Inventory import complete. %d items created, %d items skipped, %d movements posted.',
                $summary['items_created'],
                $summary['items_skipped'],
                $summary['movements_created'],
            ));
    }
}
