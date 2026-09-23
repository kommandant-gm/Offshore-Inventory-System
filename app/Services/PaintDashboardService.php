<?php
namespace App\Services;

use App\Models\MiriPaintItem;

class PaintDashboardService
{
    public function data(?string $company = null): array
    {
        app(PaintStockLedger::class)->rollover(app(BranchContext::class)->id());
        $query = MiriPaintItem::query()->companyFilter($company);
        $today = today()->toDateString();
        $soon = today()->addDays(30)->toDateString();
        $dates = (array) (clone $query)->selectRaw("COUNT(*) as total,
            COALESCE(SUM(CASE WHEN date_status = 'unconfirmed' THEN 1 ELSE 0 END), 0) as unconfirmed,
            COALESCE(SUM(CASE WHEN date_status = 'confirmed' AND best_before_date < ? THEN 1 ELSE 0 END), 0) as expired,
            COALESCE(SUM(CASE WHEN date_status = 'confirmed' AND best_before_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as due_30_days,
            COALESCE(SUM(CASE WHEN date_status = 'confirmed' AND best_before_date > ? THEN 1 ELSE 0 END), 0) as beyond_30_days",
            [$today, $today, $soon, $soon])->toBase()->first();
        $dates = array_map('intval', $dates);
        $dates['no_best_before'] = $dates['total'] - $dates['unconfirmed'] - $dates['expired'] - $dates['due_30_days'] - $dates['beyond_30_days'];
        $group = fn ($column) => (clone $query)->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not recorded') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not recorded')")->orderByDesc('total')->orderBy('label')->get();
        return [
            'summary' => [...$dates, 'duplicates' => (clone $query)->duplicateBatch()->count(),
                'review' => (clone $query)->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateBatch()))->count()],
            'stock' => app(PaintStockSummary::class)->data($query),
            'quantities' => app(PaintQuantitySummary::class)->data($query),
            'types' => $group('section_2'), 'locations' => $group('current_location'),
            'attention' => (clone $query)->expiryEligible()->where('best_before_date', '<=', $soon)
                ->orderBy('best_before_date')->orderBy('id')->limit(6)->get(['id', 'description', 'batch_no', 'current_location', 'best_before_date'])
                ->map(function ($item) {
                    $data = $item->toArray();
                    $data['days_remaining'] = (int) today()->diffInDays($item->best_before_date, false);
                    return $data;
                }),
            'recent' => (clone $query)->latest('updated_at')->orderByDesc('id')->limit(6)
                ->get(['id', 'description', 'batch_no', 'section_2', 'current_location', 'date_status']),
        ];
    }
}
