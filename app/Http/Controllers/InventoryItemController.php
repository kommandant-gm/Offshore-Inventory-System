<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryBalance;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
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

    public function index(Request $request): Response
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
                ->with(['category', 'defaultLocation'])
                ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
                ->orderBy('description')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'category' => $item->category->name,
                    'uom' => $item->uom,
                    'location' => $item->defaultLocation?->name,
                    'opening_stock' => $item->opening_stock,
                    'current_stock' => round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2),
                    'standard_cost' => $item->standard_cost,
                    'minimum_stock' => $item->minimum_stock,
                    'rack_no' => $item->rack_no,
                    'active' => $item->active,
                ]);

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

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::create($request->validated());

        return redirect()
            ->route('assets.index', ['category' => $item->category_id])
            ->with('success', 'Stock item created.');
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return back()->with('success', 'Stock item updated.');
    }

    public function show(InventoryItem $item): Response
    {
        abort_unless(request()->user()?->canRead('assets'), 403);

        $item->load(['category', 'defaultLocation', 'transactions.location', 'transactions.sourceLocation', 'transactions.destinationLocation', 'transactions.creator']);

        return Inertia::render('Assets/Show', [
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'category' => $item->category->name,
                'uom' => $item->uom,
                'location' => $item->defaultLocation?->name,
                'opening_stock' => $item->opening_stock,
                'current_stock' => round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2),
                'standard_cost' => $item->standard_cost,
                'minimum_stock' => $item->minimum_stock,
                'rack_no' => $item->rack_no,
                'remarks' => $item->remarks,
                'transactions' => $item->transactions()
                    ->with(['location', 'sourceLocation', 'destinationLocation', 'creator'])
                    ->latest('transaction_date')
                    ->latest('id')
                    ->get()
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
