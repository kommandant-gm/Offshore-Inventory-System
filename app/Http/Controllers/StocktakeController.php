<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\RecordInventoryTransactionAction;
use App\Enums\InventoryTransactionType;
use App\Http\Requests\StoreStocktakeRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Stocktake;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\InventoryLocationBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StocktakeController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('movements'), 403);

        return Inertia::render('Stocktakes/Index', [
            'stocktakes' => Stocktake::query()
                ->with(['location', 'creator'])
                ->withCount('items')
                ->latest('stocktake_date')
                ->latest('id')
                ->paginate(12)
                ->through(fn (Stocktake $stocktake) => [
                    'id' => $stocktake->id,
                    'reference_no' => $stocktake->reference_no,
                    'stocktake_date' => $stocktake->stocktake_date?->format('Y-m-d'),
                    'location' => $stocktake->location?->name,
                    'status' => $stocktake->status,
                    'items_count' => $stocktake->items_count,
                    'created_by' => $stocktake->creator?->name,
                    'completed_at' => $stocktake->completed_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function create(InventoryLocationBalanceService $locationBalanceService, DocumentNumberService $numberService): Response
    {
        abort_unless(request()->user()?->canEdit('movements'), 403);

        $items = InventoryItem::query()
            ->with(['locationBalances'])
            ->orderBy('item_code')
            ->get();

        return Inertia::render('Stocktakes/Create', [
            'locations' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => [
                    'value' => $location->id,
                    'label' => "{$location->code} - {$location->name}",
                ]),
            'items' => $items->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'uom' => $item->uom,
                'system_quantity' => round((float) $item->locationBalances->sum('quantity'), 2),
                'location_quantities' => $item->locationBalances
                    ->mapWithKeys(fn ($balance) => [$balance->location_id => round((float) $balance->quantity, 2)]),
                'current_location' => $locationBalanceService->currentLocationLabel($item),
            ]),
            'draftReference' => $this->draftReference($numberService),
        ]);
    }

    public function store(
        StoreStocktakeRequest $request,
        RecordInventoryTransactionAction $recordInventoryTransactionAction,
        InventoryLocationBalanceService $locationBalanceService,
        AuditLogger $auditLogger,
        DocumentNumberService $numberService,
    ): RedirectResponse {
        $user = $request->user();

        $stocktake = DB::transaction(function () use (
            $request,
            $recordInventoryTransactionAction,
            $locationBalanceService,
            $auditLogger,
            $user,
            $numberService,
        ) {
            $stocktake = Stocktake::query()->create([
                'reference_no' => $this->draftReference($numberService, true),
                'stocktake_date' => $request->date('stocktake_date'),
                'location_id' => $request->integer('location_id'),
                'status' => 'completed',
                'remarks' => $request->validated('remarks'),
                'created_by' => $user?->id,
                'completed_by' => $user?->id,
                'completed_at' => now(),
            ]);

            foreach ($request->validated('items') as $entry) {
                $item = InventoryItem::query()
                    ->with(['locationBalances'])
                    ->findOrFail($entry['inventory_item_id']);

                $systemQuantity = round(
                    $locationBalanceService->quantityAtLocation($item, $request->integer('location_id')),
                    2
                );
                $countedQuantity = round((float) $entry['counted_quantity'], 2);
                $variance = round($countedQuantity - $systemQuantity, 2);
                $adjustmentTransaction = null;

                if (abs($variance) > 0.00001) {
                    $adjustmentTransaction = $recordInventoryTransactionAction->persist($item, [
                        'transaction_date' => $request->date('stocktake_date')->format('Y-m-d'),
                        'location_id' => $request->integer('location_id'),
                        'source_location_id' => null,
                        'destination_location_id' => null,
                        'transaction_type' => InventoryTransactionType::PhysicalAdjustment->value,
                        'quantity' => $variance,
                        'unit_cost' => (float) $item->standard_cost,
                        'po_no' => null,
                        'do_no' => null,
                        'cog_issued_out' => null,
                        'cog_received' => null,
                        'remarks' => trim("Stocktake {$stocktake->reference_no}. ".($entry['remarks'] ?? '')),
                    ], $user->id);
                }

                $stocktake->items()->create([
                    'inventory_item_id' => $item->id,
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => $countedQuantity,
                    'variance_quantity' => $variance,
                    'adjustment_transaction_id' => $adjustmentTransaction?->id,
                    'remarks' => $entry['remarks'] ?? null,
                ]);
            }

            $auditLogger->record(
                module: 'movements',
                event: 'stocktake_completed',
                summary: "Completed stocktake {$stocktake->reference_no}.",
                auditable: $stocktake,
                after: [
                    'location_id' => $stocktake->location_id,
                    'items_count' => $stocktake->items()->count(),
                ],
                user: $user,
                request: $request,
            );

            return $stocktake;
        });

        return redirect()
            ->route('stocktakes.show', $stocktake)
            ->with('success', "Stocktake {$stocktake->reference_no} completed.");
    }

    public function show(Stocktake $stocktake): Response
    {
        abort_unless(request()->user()?->canRead('movements'), 403);

        $stocktake->load(['location', 'items.item', 'items.adjustmentTransaction', 'creator', 'completer']);

        return Inertia::render('Stocktakes/Show', [
            'stocktake' => [
                'id' => $stocktake->id,
                'reference_no' => $stocktake->reference_no,
                'stocktake_date' => $stocktake->stocktake_date?->format('Y-m-d'),
                'location' => $stocktake->location?->name,
                'status' => $stocktake->status,
                'remarks' => $stocktake->remarks,
                'created_by' => $stocktake->creator?->name,
                'completed_by' => $stocktake->completer?->name,
                'completed_at' => $stocktake->completed_at?->format('Y-m-d H:i'),
                'items' => $stocktake->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_code' => $item->item?->item_code,
                    'description' => $item->item?->description,
                    'uom' => $item->item?->uom,
                    'system_quantity' => $item->system_quantity,
                    'counted_quantity' => $item->counted_quantity,
                    'variance_quantity' => $item->variance_quantity,
                    'adjustment_transaction_id' => $item->adjustment_transaction_id,
                    'remarks' => $item->remarks,
                ]),
            ],
        ]);
    }

    private function draftReference(DocumentNumberService $numberService, bool $reserve = false): string
    {
        $prefix = 'STK/'.now()->format('ym').'/';
        $key = 'stocktake:'.now()->format('ym');
        $latest = Stocktake::query()->where('reference_no', 'like', "{$prefix}%")->orderByDesc('id')->value('reference_no');
        $minimumNext = $latest ? ((int) str($latest)->afterLast('/')->value()) + 1 : 1;

        return $reserve
            ? $numberService->next($key, $prefix, minimumNext: $minimumNext)
            : $prefix.str_pad((string) max(
                $minimumNext,
                (int) $numberService->preview($key, ''),
            ), 3, '0', STR_PAD_LEFT);
    }
}
