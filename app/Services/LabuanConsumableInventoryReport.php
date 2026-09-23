<?php

namespace App\Services;

use App\Models\MiriConstructionItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LabuanConsumableInventoryReport extends ConsumableInventoryReport
{
    public const COLUMNS = [
        'number' => 'No', 'description' => 'Description', 'brand' => 'Brand', 'opening' => 'Opening Stock',
        'received' => 'Quantities Received', 'source' => 'Demob From Loc / (PO NO)', 'received_dates' => 'Date Received',
        'issued' => 'Quantities Out', 'destination' => 'Mob To Loc', 'issued_dates' => 'Date Mob Out',
        'misc' => 'Other Misc.', 'closing' => 'Balance Stock', 'unit' => 'Unit', 'rack' => 'RACK NO.', 'remarks' => 'Remarks',
    ];

    protected function locationCode(): string
    {
        return 'LBN';
    }

    protected function reportLabel(): string
    {
        return 'Labuan Consumable';
    }

    protected function scopeNote(): string
    {
        return 'Includes Labuan/LBN construction records in the Consumable and PPE categories, regardless of Section 1.';
    }

    protected function matches(MiriConstructionItem $item): bool
    {
        return in_array(strtoupper(trim($item->category ?? '')), ['CONSUMABLE', 'PPE'], true);
    }

    public function generate(int $branch, string $month): array
    {
        $report = parent::generate($branch, $month);
        if ($report['unavailable'] || $report['rows'] === []) {
            return $report;
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Kuala_Lumpur')->utc();
        $end = $start->setTimezone('Asia/Kuala_Lumpur')->addMonth()->utc();
        $items = DB::table('miri_construction_items')->where('branch_id', $branch)->whereIn('id', array_column($report['rows'], 'id'))->get()->keyBy('id');
        $movements = DB::table('miri_construction_stock_movements as m')
            ->leftJoin('miri_cog_items as l', 'l.id', '=', 'm.cog_item_id')
            ->leftJoin('miri_cogs as c', 'c.id', '=', 'l.miri_cog_id')
            ->where('m.branch_id', $branch)->whereIn('m.construction_item_id', $items->keys())
            ->where('m.created_at', '>=', $start->toDateTimeString())->where('m.created_at', '<', $end->toDateTimeString())
            ->orderBy('m.created_at')->orderBy('m.id')->get(['m.*', 'c.from_location', 'c.to_location'])->groupBy('construction_item_id');
        $report['notes'][3] = 'IN includes receipts, backloads and transfers in. OUT includes issues, transfers out and supplier returns. Other Misc. is the signed total of corrections and write-offs. Opening verification is not a receipt.';
        $report['notes'][5] = 'This template has no price columns. Dates refer to stock posting dates in Malaysia time; multiple movement dates/locations are listed together.';
        $report['notes'][] = 'Location filtering uses current register location. The warehouse heading identifies Labuan as a whole; the specific recorded store and rack are retained on each row.';
        foreach ($report['rows'] as &$row) {
            $activity = $movements->get($row['id'], collect());
            $in = $activity->whereIn('kind', ['receipt', 'backload', 'transfer_in']);
            $out = $activity->whereIn('kind', ['issue', 'transfer_out', 'supplier_return']);
            $known = $row['received'] !== null;
            $sum = fn ($entries) => $known ? $entries->sum(fn ($entry) => (int) round((float) $entry->quantity * 1000)) / 1000 : null;
            $row['received'] = $sum($in);
            $row['issued'] = $known ? -$sum($out) : null;
            $row['misc'] = $sum($activity->whereIn('kind', ['correction', 'writeoff']));
            $join = fn ($values) => $values->filter()->unique()->implode("\n");
            $row['source'] = $join($in->map(fn ($entry) => implode(' / ', array_filter([$entry->from_location, $entry->reference]))));
            $row['destination'] = $join($out->pluck('to_location'));
            $dates = fn ($entries) => $join($entries->map(fn ($entry) => CarbonImmutable::parse($entry->created_at, 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y')));
            $row['received_dates'] = $dates($in);
            $row['issued_dates'] = $dates($out);
            $item = $items->get($row['id']);
            $row['rack'] = $item->storage_rack;
            $row['remarks'] = implode('; ', array_filter([$row['remarks'], $item->current_location ? 'Store: '.$item->current_location : null]));
        }
        unset($row);

        return $report;
    }
}
