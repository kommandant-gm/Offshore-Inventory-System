<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\InventoryLocationBalanceService;
use App\Support\InventoryItemProjector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('assets'), 403);

        $categories = Category::query()
            ->whereIn('type', ['asset', 'both'])
            ->orderBy('name')
            ->get();

        $selectedCategory = $categories->firstWhere('id', (int) $request->integer('category'))
            ?? $categories->first();

        return Inertia::render('Assets/Create', [
            'selectedCategoryId' => $selectedCategory?->id,
            'categoryOptions' => $categories->map(fn (Category $category) => [
                'value' => $category->id,
                'label' => $category->name,
                'code' => $category->code,
            ]),
            'locationOptions' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => "{$location->code} - {$location->name}"]),
        ]);
    }

    public function index(Request $request, InventoryItemProjector $itemProjector): Response
    {
        abort_unless($request->user()?->canRead('assets'), 403);

        $categories = Category::query()
            ->whereIn('type', ['asset', 'both'])
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->get();

        $selectedCategory = $categories->firstWhere('id', (int) $request->integer('category'))
            ?? $categories->first();

        $items = InventoryItem::query()
                ->with(['category', 'defaultLocation', 'locationBalances.location'])
                ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
                ->orderBy('description')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (InventoryItem $item) => $itemProjector->listPayload($item));

        return Inertia::render('Assets/Index', [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'item_count' => $category->inventory_items_count,
                'active' => $category->active,
            ]),
            'selectedCategoryId' => $selectedCategory?->id,
            'items' => $items,
        ]);
    }

    public function store(
        StoreInventoryItemRequest $request,
        InventoryLocationBalanceService $locationBalanceService,
        AuditLogger $auditLogger,
    ): RedirectResponse
    {
        $item = InventoryItem::create($request->validated());
        $locationBalanceService->initializeItem($item->fresh(['locationBalances']));
        $auditLogger->record(
            module: 'assets',
            event: 'created',
            summary: "Created stock item {$item->item_code}.",
            auditable: $item,
            after: $item->fresh()->only([
                'item_code',
                'description',
                'category_id',
                'default_location_id',
                'opening_stock',
                'standard_cost',
                'minimum_stock',
                'rack_no',
                'active',
            ]),
            user: $request->user(),
            request: $request,
        );

        return redirect()
            ->route('assets.index', ['category' => $item->category_id])
            ->with('success', 'Stock item created.');
    }

    public function update(
        UpdateInventoryItemRequest $request,
        InventoryItem $item,
        InventoryLocationBalanceService $locationBalanceService,
        AuditLogger $auditLogger,
    ): RedirectResponse
    {
        $before = $item->only([
            'item_code',
            'description',
            'category_id',
            'default_location_id',
            'opening_stock',
            'standard_cost',
            'minimum_stock',
            'rack_no',
            'active',
        ]);

        $item->update($request->validated());
        $locationBalanceService->syncItem($item->fresh(['transactions', 'locationBalances']));
        $auditLogger->record(
            module: 'assets',
            event: 'updated',
            summary: "Updated stock item {$item->item_code}.",
            auditable: $item,
            before: $before,
            after: $item->fresh()->only([
                'item_code',
                'description',
                'category_id',
                'default_location_id',
                'opening_stock',
                'standard_cost',
                'minimum_stock',
                'rack_no',
                'active',
            ]),
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Stock item updated.');
    }

    public function show(InventoryItem $item, InventoryItemProjector $itemProjector): Response
    {
        abort_unless(request()->user()?->canRead('assets'), 403);

        $item->load([
            'category',
            'defaultLocation',
            'locationBalances.location',
            'transactions' => fn ($query) => $query
                ->with(['location', 'sourceLocation', 'destinationLocation', 'creator'])
                ->latest('transaction_date')
                ->latest('id'),
        ]);

        return Inertia::render('Assets/Show', [
            'item' => [
                ...$itemProjector->listPayload($item),
                'location_balances' => $itemProjector->locationBalancePayload($item),
                'remarks' => $item->remarks,
                'transactions' => $item->transactions
                    ->map(fn ($transaction) => [
                        'id' => $transaction->id,
                        'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                        'transaction_type' => $transaction->transaction_type->value,
                        'quantity' => $transaction->quantity,
                        'unit_cost' => $transaction->unit_cost,
                        'total_value' => $transaction->total_value,
                        'location' => $transaction->location?->name,
                        'source_location' => $transaction->sourceLocation?->name,
                        'destination_location' => $transaction->destinationLocation?->name,
                        'remarks' => $transaction->remarks,
                        'created_by' => $transaction->creator?->name,
                    ]),
            ],
        ]);
    }
}
