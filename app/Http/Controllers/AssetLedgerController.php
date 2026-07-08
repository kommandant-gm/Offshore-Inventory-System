<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AssetLedgerReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetLedgerController extends Controller
{
    public function index(Request $request, AssetLedgerReportService $ledgerReportService): Response
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
            'rows' => $ledgerReportService->rows($year, $month, $selectedCategory?->id),
        ]);
    }
}
