<?php

namespace App\Services;

use App\Models\MiriConstructionItem;
use App\Support\InventoryReportColumns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CidbInventoryReport
{
    public const COLUMNS = InventoryReportColumns::COLUMNS;

    public function generate(int $branch, string $month): array
    {
        $notes = [
            'Includes Bintulu construction records with CIDB in Category, Section 1 or Section 2. WQT TRAINING is not assumed to mean CIDB.',
            'Monthly boundaries use Malaysia time. Only posted construction stock movements are counted; drafts do not change stock.',
            'Opening and closing balances come from ledger balances at month boundaries. A current unverified register balance may appear as closing stock, with its coverage noted below.',
            'Total received counts purchase receipts only. Total issued counts issues only. Backloads, transfers, supplier returns, write-offs and corrections are listed separately in Remarks.',
            'Current item descriptions, brands, locations, PO and DO references are shown. They are not historical metadata snapshots.',
            'Recorded unit price and closing stock value are shown only for the current month. Historical prices and unsaved opening/received/issued values remain blank; no valuation is inferred.',
            'Blank means unavailable. Zero means zero recorded activity. Grand totals are shown only for complete data, and quantities are not added across different or missing units.',
        ];
        foreach (['miri_construction_items', 'miri_construction_stock_movements'] as $table) {
            if (! Schema::hasTable($table)) {
                return ['rows' => [], 'notes' => $notes, 'unavailable' => 'CIDB reporting requires the construction register and stock-history database migrations.'];
            }
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Kuala_Lumpur')->utc();
        $end = $start->setTimezone('Asia/Kuala_Lumpur')->addMonth()->utc();
        $current = $month === now('Asia/Kuala_Lumpur')->format('Y-m');
        $items = MiriConstructionItem::withoutGlobalScopes()->where('branch_id', $branch)->orderBy('description')->orderBy('id')->get()
            ->filter(fn ($item) => app(PaintQuantitySummary::class)->location($item->current_location) === 'BTU'
                && collect([$item->category, $item->section_1, $item->section_2])->contains(fn ($value) => preg_match('/\bCIDB\b/i', $value ?? '') === 1));
        $history = DB::table('miri_construction_stock_movements')->where('branch_id', $branch)
            ->whereIn('construction_item_id', $items->modelKeys())->where('created_at', '<', $end->toDateTimeString())
            ->orderBy('created_at')->orderBy('id')->get()->groupBy('construction_item_id');
        $rows = [];
        foreach ($items as $item) {
            $ledger = $history->get($item->id, collect());
            if ($ledger->isEmpty() && $item->created_at?->gte($end)) {
                continue;
            }
            $before = $ledger->filter(fn ($entry) => $entry->created_at < $start->toDateTimeString())->last();
            $last = $ledger->last();
            $activity = $ledger->filter(fn ($entry) => $entry->created_at >= $start->toDateTimeString());
            $number = count($rows) + 1;
            $money = fn ($value) => $value === null ? null : (float) $value;
            $opening = $before ? $money($before->balance_after) : null;
            $closing = $last ? $money($last->balance_after) : ($current ? $money($item->stock_balance) : null);
            $known = $last !== null;
            $sum = fn ($kind, $sign = 1) => $known ? $sign * $activity->where('kind', $kind)->sum(fn ($entry) => (int) round((float) $entry->quantity * 1000)) / 1000 : null;
            $remarks = [];
            if ($item->tag_no) {
                $remarks[] = 'Tag: '.$item->tag_no;
            }
            if ($item->serial_no) {
                $remarks[] = 'S/N: '.$item->serial_no;
            }
            foreach (['backload' => 'Backload', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out', 'supplier_return' => 'Supplier return', 'writeoff' => 'Write-off', 'correction' => 'Correction'] as $kind => $label) {
                $value = $sum($kind);
                if ($value !== null && $value != 0) {
                    $remarks[] = $label.': '.$value;
                }
            }
            if (! $before) {
                $notes[] = 'Item '.$number.': opening history unavailable; monthly movements may have partial coverage.';
            }
            if (! $last) {
                $notes[] = 'Item '.$number.': no confirmed stock history. '.($current ? 'Closing stock is the unverified recorded balance.' : 'Historical closing balance is unavailable.');
            }
            $rows[] = [
                'id' => $item->id, 'number' => $number, 'description' => $item->description, 'brand' => $item->model_brand,
                'opening' => $opening, 'received' => $sum('receipt'), 'issued' => $sum('issue', -1), 'closing' => $closing,
                'unit' => $last?->unit ?? $item->unit, 'unit_price' => $current ? $money($item->unit_price) : null,
                'opening_value' => null, 'received_value' => null, 'issued_value' => null,
                'closing_value' => $current ? $money($item->closing_value) : null,
                'location' => implode(' / ', array_filter([$item->current_location, $item->storage_rack])),
                'po' => $item->po_reference, 'do' => $item->do_reference, 'remarks' => implode('; ', $remarks),
            ];
        }

        return ['rows' => $rows, 'notes' => $notes, 'unavailable' => null];
    }
}
