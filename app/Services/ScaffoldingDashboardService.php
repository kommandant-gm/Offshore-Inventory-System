<?php

namespace App\Services;

use App\Models\MajorEquipment;
use Illuminate\Support\Facades\DB;

class ScaffoldingDashboardService
{
    public function data(?string $company = null): array
    {
        $items = MajorEquipment::query()->scaffolding()->companyFilter($company);
        $ids = (clone $items)->select('miri_inventory_items.id');
        $accounts = DB::table('scaffolding_stock_accounts')->whereIn('equipment_id', $ids);
        $total = (clone $items)->count();
        $verified = (clone $accounts)->count();
        return [
            'summary' => ['records' => $total, 'verified' => $verified, 'unverified' => $total * count(ScaffoldingStockLedger::YARDS) - $verified,
                'zero_balance' => (clone $accounts)->where('balance', 0)->count()],
            'yards' => collect(ScaffoldingStockLedger::YARDS)->map(function ($yard) use ($accounts, $total) {
                $query = (clone $accounts)->where('yard', $yard);
                $verified = (clone $query)->count();
                return ['yard' => $yard, 'verified' => $verified, 'unverified' => $total - $verified,
                    'balances' => $query->selectRaw('unit, COUNT(*) as records, SUM(balance) as quantity')
                        ->groupBy('unit')->orderBy('unit')->get()];
            }),
            'subcategories' => (clone $items)->selectRaw("COALESCE(NULLIF(TRIM(section_2), ''), 'Not recorded') as label, COUNT(*) as total")
                ->groupByRaw("COALESCE(NULLIF(TRIM(section_2), ''), 'Not recorded')")->orderByDesc('total')->orderBy('label')->get(),
            'recent' => DB::table('scaffolding_stock_movements as m')
                ->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')
                ->join('miri_inventory_items as i', 'i.id', '=', 'a.equipment_id')
                ->whereIn('i.id', $ids)->orderByDesc('m.id')->limit(10)
                ->get(['m.id', 'a.equipment_id', 'i.description', 'a.yard', 'a.unit', 'm.kind', 'm.quantity', 'm.balance_after', 'm.reference', 'm.document_date', 'm.contract', 'm.destination']),
            'needsVerification' => (clone $items)->where(function ($query) {
                foreach (ScaffoldingStockLedger::YARDS as $yard) {
                    $query->orWhereNotExists(fn ($q) => $q->selectRaw('1')->from('scaffolding_stock_accounts as a')
                        ->whereColumn('a.equipment_id', 'miri_inventory_items.id')->where('a.yard', $yard));
                }
            })->orderBy('description')->orderBy('id')->limit(10)->get(['id', 'description', 'section_2', 'unit']),
        ];
    }
}
