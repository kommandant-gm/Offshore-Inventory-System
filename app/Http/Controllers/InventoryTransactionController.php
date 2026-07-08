<?php

namespace App\Http\Controllers;

use App\Actions\Cogs\CreateCogAction;
use App\Actions\Cogs\CreateCogFromMovementAction;
use App\Actions\Inventory\RecordInventoryTransactionAction;
use App\Enums\InventoryTransactionType;
use App\Http\Requests\StoreInventoryTransactionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTransactionController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('movements'), 403);

        return Inertia::render('Assets/Movements/Index', [
            'transactions' => InventoryTransaction::query()
                ->with(['item', 'location', 'sourceLocation', 'destinationLocation', 'creator', 'cog'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20)
                ->through(fn (InventoryTransaction $transaction) => [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                    'item_code' => $transaction->item->item_code,
                    'description' => $transaction->item->description,
                    'uom' => $transaction->item->uom,
                    'transaction_type' => $transaction->transaction_type->value,
                    'quantity' => $transaction->quantity,
                    'unit_cost' => $transaction->unit_cost,
                    'total_value' => $transaction->total_value,
                    'location' => $transaction->location?->name,
                    'source_location' => $transaction->sourceLocation?->name,
                    'destination_location' => $transaction->destinationLocation?->name,
                    'cog_id' => $transaction->cog_id,
                    'cog_no' => $transaction->cog?->cog_no ?? $transaction->cog_issued_out,
                    'created_by' => $transaction->creator?->name,
                    'remarks' => $transaction->remarks,
                ]),
        ]);
    }

    public function create(CreateCogAction $createCogAction): Response
    {
        abort_unless(request()->user()?->canEdit('movements'), 403);

        return Inertia::render('Assets/Movements/Create', [
            'items' => InventoryItem::query()
                ->orderBy('item_code')
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'value' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'label' => "{$item->item_code} - {$item->description}",
                    'uom' => $item->uom,
                    'unit_cost' => $item->standard_cost,
                ]),
            'locations' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => "{$location->code} - {$location->name}"]),
            'transactionTypes' => InventoryTransactionType::options(),
            'draftCogNo' => $createCogAction->draftNumber(),
        ]);
    }

    public function store(
        StoreInventoryTransactionRequest $request,
        RecordInventoryTransactionAction $recordTransactionAction,
        CreateCogFromMovementAction $createCogFromMovementAction,
        CreateCogAction $createCogAction,
    ): RedirectResponse
    {
        $item = InventoryItem::findOrFail($request->validated('item_id'));
        $validated = $request->validated();
        $cog = null;

        DB::transaction(function () use (
            $item,
            $validated,
            $request,
            $recordTransactionAction,
            $createCogFromMovementAction,
            &$cog,
        ) {
            $transaction = $recordTransactionAction->persist(
                $item,
                Arr::except($validated, ['generate_cog', 'cog']),
                $request->user()->id,
            );

            if ($request->boolean('generate_cog')) {
                $cog = $createCogFromMovementAction->persist(
                    $transaction,
                    $validated['cog'] ?? [],
                    $request->user()?->id,
                );
            }
        });

        if ($cog) {
            $createCogAction->dispatchApprovalEmail($cog);

            return redirect()
                ->route('cogs.show', $cog)
                ->with('success', "Stock item movement recorded and COG {$cog->cog_no} created.");
        }

        return redirect()->route('asset-movements.index')->with('success', 'Stock item movement recorded.');
    }
}
