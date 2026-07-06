<?php

namespace App\Http\Controllers;

use App\Enums\InventoryTransactionType;
use App\Models\Category;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canRead('ledger'), 403);

        $year = (int) ($request->integer('year') ?: now()->year);
        $month = (int) ($request->integer('month') ?: now()->month);

        $categories = Category::query()
            ->whereIn('type', ['asset', 'both'])
            ->orderBy('name')
            ->get();

        $selectedCategory = $categories->firstWhere('id', (int) $request->integer('category'))
            ?? $categories->first();

        $items = InventoryItem::query()
            ->with(['category', 'transactions' => fn ($query) => $query
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->latest('transaction_date')
                ->latest('id')])
            ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
            ->orderBy('description')
            ->get();

        return Inertia::render('Assets/Ledger/Index', [
            'filters' => [
                'year' => $year,
                'month' => $month,
                'category' => $selectedCategory?->id,
            ],
            'categories' => $categories->map(fn (Category $category) => [
                'value' => $category->id,
                'label' => "{$category->code} - {$category->name}",
            ]),
            'rows' => $items->values()->map(function (InventoryItem $item, int $index) {
                $transactions = $item->transactions;
                $unitPrice = (float) $item->standard_cost;

                $sumQty = fn (InventoryTransactionType $type) => (float) $transactions
                    ->where('transaction_type', $type)
                    ->sum('quantity');

                $sumValue = fn (InventoryTransactionType $type) => (float) $transactions
                    ->where('transaction_type', $type)
                    ->sum('total_value');

                $openingStock = (float) $item->opening_stock;
                $received = $sumQty(InventoryTransactionType::Receive);
                $issued = $sumQty(InventoryTransactionType::Issue);
                $interlocTransfer = $sumQty(InventoryTransactionType::InterlocTransfer);
                $materialReturn = $sumQty(InventoryTransactionType::MaterialReturn);
                $physicalAdjustment = $sumQty(InventoryTransactionType::PhysicalAdjustment);
                $otherMisc = $sumQty(InventoryTransactionType::OtherMisc);
                $priceAdjustmentValue = $sumValue(InventoryTransactionType::PriceAdjustment);

                $closingStock = $openingStock + $received - $issued - $interlocTransfer + $materialReturn + $physicalAdjustment + $otherMisc;

                $latestWith = fn (string $field) => optional($transactions->first(fn ($transaction) => filled($transaction->{$field})))->{$field};

                return [
                    'no' => $index + 1,
                    'description' => $item->description,
                    'opening_stock' => $openingStock,
                    'total_received' => $received,
                    'total_issued' => $issued,
                    'interloc_transfer' => $interlocTransfer,
                    'material_return' => $materialReturn,
                    'physical_adjustment' => $physicalAdjustment,
                    'price_adjustment' => $priceAdjustmentValue,
                    'other_misc' => $otherMisc,
                    'closing_stock' => $closingStock,
                    'unit_measure' => $item->uom,
                    'unit_price' => $unitPrice,
                    'opening_stock_value' => $openingStock * $unitPrice,
                    'total_received_value' => $sumValue(InventoryTransactionType::Receive),
                    'total_issued_value' => $sumValue(InventoryTransactionType::Issue),
                    'interloc_transfer_value' => $sumValue(InventoryTransactionType::InterlocTransfer),
                    'material_return_value' => $sumValue(InventoryTransactionType::MaterialReturn),
                    'physical_adjustment_value' => $sumValue(InventoryTransactionType::PhysicalAdjustment),
                    'price_adjustment_value' => $priceAdjustmentValue,
                    'other_misc_value' => $sumValue(InventoryTransactionType::OtherMisc),
                    'closing_stock_value' => ($closingStock * $unitPrice) + $priceAdjustmentValue,
                    'rack_no' => $item->rack_no,
                    'cog_issued_out' => $latestWith('cog_issued_out'),
                    'cog_received' => $latestWith('cog_received'),
                    'purchase_order_no' => $latestWith('po_no'),
                    'delivery_order_no' => $latestWith('do_no'),
                    'remarks' => $latestWith('remarks') ?: $item->remarks,
                ];
            }),
        ]);
    }
}
