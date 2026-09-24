<?php

namespace App\Services;

use App\Models\MiriPaintItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LabuanPaintInventoryReport
{
    public const COLUMNS = [
        'number' => 'No.', 'description' => 'Description', 'po' => 'PO No.', 'do' => 'DO No.',
        'received_date' => 'Received Date', 'batch' => 'Batch No.', 'dates' => 'Date of Manufacture - Best Before',
        'received_pc' => 'Received Qty (pc)', 'received_litres' => 'Received Qty (Litre)', 'rack' => 'Storage Rack No.',
        'load_location' => 'To Location', 'load_cog' => 'COG', 'load_qty' => 'Qty (Litre)', 'load_date' => 'Date', 'load_balance' => 'Balance Qty (Litre)',
        'back_location' => 'From Location', 'back_cog' => 'COG', 'back_qty' => 'Qty (Litre)', 'back_date' => 'Date', 'back_rack' => 'Storage Rack No..',
        'disposal_supplier' => 'To Supplier', 'disposal_cog' => 'COG', 'disposal_qty' => 'Qty (Litre)', 'disposal_date' => 'Date', 'remarks' => 'Remarks',
    ];

    public function generate(int $branch, array $filters): array
    {
        $base = app(PaintInventoryReport::class)->generate($branch, [...$filters, 'location' => 'LBN']);
        $notes = [
            'Includes paint records currently located in Labuan/LBN. The supplied warehouse heading is retained; each recorded location is shown in Remarks.',
            'The first posted LTR issue and first posted LTR backload in the selected stock month are shown. Cancelled issues are excluded as of the end of that month. Dates are COG document dates, falling back to Malaysia posting dates. Balance is the recorded balance immediately after that load-out.',
            'Receipt quantities are current register values, not monthly receipt totals. CAN/PCS quantities appear in pc; litres appear only when explicitly recorded in litre units. No conversion is assumed. Historical receipt quantities and received dates are unavailable.',
            'Descriptions, PO/DO references, batch, manufacture/best-before dates and racks use current register details. Backload rack is the recorded register rack, not a historical snapshot.',
            'Disposal columns remain blank because disposal-specific supplier, quantity and date are not recorded. Supplier returns and adjustments are not assumed to be disposal.',
            'Blank movement cells mean no matching tracked event, not a verified zero. Additional movements are flagged in Remarks; the template displays the first event only.',
        ];
        if ($base['unavailable']) {
            return ['rows' => [], 'notes' => $notes, 'unavailable' => $base['unavailable']];
        }
        $ids = collect($base['rows'])->pluck('id')->unique();
        $items = MiriPaintItem::withoutGlobalScopes()->where('branch_id', $branch)->whereIn('id', $ids)->orderBy('description')->orderBy('id')->get();
        $movements = DB::table('miri_paint_stock_movements as m')
            ->leftJoin('miri_cog_items as l', 'l.id', '=', 'm.cog_item_id')
            ->leftJoin('miri_cogs as c', 'c.id', '=', 'l.miri_cog_id')
            ->where('m.branch_id', $branch)->whereIn('m.paint_item_id', $ids)->where('m.period', $filters['month'].'-01')
            ->where('m.unit', 'LTR')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('miri_paint_stock_movements as reversal')
                ->whereColumn('reversal.cog_item_id', 'm.cog_item_id')->where('reversal.kind', 'cancellation')->where('reversal.period', '<=', $filters['month'].'-01'))
            ->orderBy('m.created_at')->orderBy('m.id')
            ->get(['m.*', 'c.cog_no', 'c.document_date', 'c.from_location', 'c.to_location', 'c.movement_type'])->groupBy('paint_item_id');
        $rows = [];
        $date = fn ($entry) => $entry ? CarbonImmutable::parse($entry->document_date ?: $entry->created_at, 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y') : null;
        $number = fn ($value) => $value === null ? null : (float) $value;
        foreach ($items as $item) {
            $activity = $movements->get($item->id, collect());
            $loads = $activity->where('kind', 'outbound')->where('movement_type', 'Issue out');
            $backs = $activity->where('kind', 'backload');
            $load = $loads->first();
            $back = $backs->first();
            $current = $item->stock_period === $filters['month'].'-01';
            $unit = strtoupper(trim($item->unit ?? ''));
            $remarks = ['Location: '.$item->current_location];
            if ($loads->count() > 1 || $backs->count() > 1) {
                $remarks[] = 'Additional LTR movements: '.max(0, $loads->count() - 1).' load-out; '.max(0, $backs->count() - 1).' backload';
            }
            if ($item->date_status === 'unconfirmed') {
                $remarks[] = 'Manufacture / best-before date type unconfirmed';
            }
            $rows[] = [
                ...array_fill_keys(array_keys(self::COLUMNS), null),
                'id' => $item->id, 'number' => count($rows) + 1, 'description' => $item->description,
                'po' => $item->po_reference, 'do' => $item->do_reference, 'batch' => $item->batch_no,
                'dates' => $item->date_status === 'confirmed'
                    ? implode("\n", array_filter([$item->manufacture_date ? 'Mfg: '.$item->manufacture_date->format('d/m/Y') : null, $item->best_before_date ? 'Best before: '.$item->best_before_date->format('d/m/Y') : null]))
                    : $item->original_date,
                'received_pc' => $current && in_array($unit, ['CAN', 'CANS', 'PC', 'PCS']) ? $number($item->stock_in_qty) : null,
                'received_litres' => $current && in_array($unit, ['LTR', 'L', 'LITRE', 'LITRES', 'LITER', 'LITERS']) ? $number($item->stock_in_qty) : null,
                'rack' => $item->storage_rack,
                'load_location' => $load?->to_location, 'load_cog' => $load?->cog_no,
                'load_qty' => $load?->quantity === null ? null : -(float) $load->quantity,
                'load_date' => $date($load), 'load_balance' => $number($load?->balance_after),
                'back_location' => $back?->from_location, 'back_cog' => $back?->cog_no,
                'back_qty' => $number($back?->quantity), 'back_date' => $date($back),
                'back_rack' => $back ? $item->backload_rack : null, 'remarks' => implode('; ', $remarks),
            ];
        }

        return ['rows' => $rows, 'notes' => $notes, 'unavailable' => null];
    }
}
