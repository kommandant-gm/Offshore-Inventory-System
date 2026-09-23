<?php

namespace App\Services;

use App\Models\MiriConstructionItem;

class ConstructionDashboardService
{
    public function data(?string $company = null): array
    {
        $query = MiriConstructionItem::query()->companyFilter($company);
        $summary = (clone $query)->selectRaw("COUNT(*) as total,
            COUNT(stock_balance) as balance_recorded,
            COALESCE(SUM(CASE WHEN stock_balance = 0 THEN 1 ELSE 0 END), 0) as zero_balance,
            COUNT(certificate_due_date) as certificate_dates")->toBase()->first();
        $group = fn ($column) => (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not recorded') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not recorded')")
            ->orderByDesc('total')->orderBy('label')->get();

        return [
            'summary' => [...array_map('intval', (array) $summary),
                'duplicates' => (clone $query)->duplicateTag()->count(),
                'review' => (clone $query)->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateTag()))->count()],
            'categories' => $group('category'),
            'locations' => $group('current_location'),
            'sections' => $group('section_1'),
            'statuses' => $group('status'),
            'stockGroups' => (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(category), ''), 'Not recorded') as category,
                    COALESCE(NULLIF(TRIM(current_location), ''), 'Not recorded') as location,
                    NULLIF(UPPER(TRIM(unit)), '') as unit,
                    COUNT(*) as records, COUNT(stock_balance) as recorded,
                    SUM(stock_balance) as stock")
                ->groupByRaw("COALESCE(NULLIF(TRIM(category), ''), 'Not recorded'), COALESCE(NULLIF(TRIM(current_location), ''), 'Not recorded'), NULLIF(UPPER(TRIM(unit)), '')")
                ->orderBy('category')->orderBy('location')->orderBy('unit')->get()
                ->map(fn ($row) => [
                    'category' => $row->category, 'location' => $row->location, 'unit' => $row->unit,
                    'records' => (int) $row->records, 'recorded' => (int) $row->recorded,
                    'stock' => $row->stock === null || $row->unit === null ? null : (float) $row->stock,
                ]),
            'recent' => (clone $query)->latest('updated_at')->orderByDesc('id')->limit(6)
                ->get(['id', 'description', 'tag_no', 'category', 'stock_balance', 'unit', 'current_location']),
        ];
    }
}
