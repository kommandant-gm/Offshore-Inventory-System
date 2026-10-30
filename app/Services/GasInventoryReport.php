<?php

namespace App\Services;

use App\Models\MiriRentalItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

class GasInventoryReport
{
    public const COLUMNS = [
        'number' => 'No.', 'description' => 'Description', 'po' => 'PO / SR No.', 'do' => 'DO No.',
        'received_date' => 'DO / On-hire Date', 'identifier' => 'Serial / Tag No.', 'supplier' => 'Supplier',
        'unit' => 'Register Unit', 'status' => 'Current Status', 'location' => 'Current Location',
        'load_location' => 'To Location', 'load_cog' => 'COG', 'load_qty' => 'Qty', 'load_date' => 'Date', 'load_unit' => 'Unit',
        'back_location' => 'From Location', 'back_cog' => 'COG', 'back_qty' => 'Qty', 'back_date' => 'Date', 'back_unit' => 'Unit',
        'return_supplier' => 'To Supplier', 'return_cog' => 'COG', 'return_qty' => 'Qty / Unit', 'return_date' => 'Date', 'remarks' => 'Remarks',
    ];

    public function generate(int $branch, array $filters): array
    {
        $notes = [
            'All locations. Includes the same BIG and LGM supplier records as the Gas Register, within the active Miri branch.',
            'Register details are current values, not historical snapshots. The selected month filters load-out, backload and supplier-return events only.',
            'Each group displays the first signed or confirmed Internal Issue Note by document date in the selected month. Draft and cancelled notes are excluded. Additional qualifying events are counted in Remarks.',
            'Where no qualifying tracked event exists, a dated register COG reference in the selected month is shown. Its quantity remains blank. References belonging to tracked notes are never replayed as register-only movements.',
            'Quantities and units come from COG lines. No cylinder count, litres conversion, opening stock or balance is inferred: the gas register has no verified quantity ledger.',
            'DO / on-hire dates are recorded document dates, not verified receipt timestamps. Supplier returns are not labelled as disposal. Blank means unavailable or no qualifying event.',
        ];
        foreach (['miri_rental_items', 'miri_cogs', 'miri_cog_items'] as $table) {
            if (! Schema::hasTable($table)) return ['rows' => [], 'notes' => $notes, 'unavailable' => 'Gas reporting requires the gas register and Internal Issue Note database tables.'];
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'Asia/Kuala_Lumpur')->toDateString();
        $end = CarbonImmutable::parse($start)->addMonth()->toDateString();
        $items = MiriRentalItem::withoutGlobalScopes()->where('branch_id', $branch)->forRegister(true)
            ->orderBy('description')->orderBy('id')->get();
        $events = DB::table('miri_cog_items as l')->join('miri_cogs as c', 'c.id', '=', 'l.miri_cog_id')
            ->where('l.branch_id', $branch)->where('c.branch_id', $branch)->where('l.item_type', 'Rental')->whereIn('l.item_id', $items->modelKeys())
            ->orderBy('c.document_date')->orderBy('c.id')->orderBy('l.id')
            ->get(['l.item_id', 'l.quantity', 'l.unit', 'c.cog_no', 'c.status', 'c.movement_type', 'c.document_date', 'c.from_location', 'c.to_location', 'c.destination'])->groupBy('item_id');
        $rows = [];
        foreach ($items as $item) {
            $all = $events->get($item->id, collect());
            $activity = $all->filter(fn ($e) => in_array(strtolower($e->status ?? ''), ['signed', 'confirmed'], true) && $e->document_date >= $start && $e->document_date < $end);
            $remarks = array_filter([$item->remarks, $item->project_contract ? 'Project / contract: '.$item->project_contract : null]);
            $row = [...array_fill_keys(array_keys(self::COLUMNS), null),
                'id' => $item->id, 'number' => count($rows) + 1, 'description' => $item->description,
                'po' => $item->po_or_sr_no, 'do' => $item->do_no, 'identifier' => $item->serial_tag_equipment_no,
                'supplier' => $item->supplier, 'unit' => $item->unit, 'status' => $item->status, 'location' => $item->current_location,
                'received_date' => implode("\n", array_filter([$item->do_date ? 'DO: '.$item->do_date->format('d/m/Y') : null,
                    $item->onhire_certificate_date ? 'On-hire: '.$item->onhire_certificate_date->format('d/m/Y') : null])),
            ];
            foreach (['load' => ['Issue out', 'issue_out_cog_no', 'issue_out_cog_date'],
                'back' => ['Received backload', 'received_backload_cog_no', 'received_backload_cog_date'],
                'return' => ['Return to supplier', 'return_cog_no', 'return_cog_date']] as $prefix => [$kind, $ref, $date]) {
                $matches = $activity->where('movement_type', $kind);
                $event = $matches->first();
                if ($event) {
                    $row[$prefix.'_cog'] = $event->cog_no;
                    $row[$prefix.'_date'] = CarbonImmutable::parse($event->document_date)->format('d/m/Y');
                    $row[$prefix.'_qty'] = $event->quantity === null ? null : (float) $event->quantity;
                    if ($prefix === 'return') {
                        $row['return_supplier'] = $event->destination ?: $event->to_location;
                        if ($event->quantity !== null) $row['return_qty'] = trim((float) $event->quantity.' '.($event->unit ?: '(unit not recorded)'));
                    } else {
                        $row[$prefix.'_unit'] = $event->unit;
                        $row[$prefix.'_location'] = $prefix === 'back' ? $event->from_location : ($event->destination ?: $event->to_location);
                    }
                    if ($matches->count() > 1) $remarks[] = 'Additional '.$kind.' entries: '.($matches->count() - 1);
                } elseif ($item->$ref && $item->$date && $item->$date->format('Y-m-d') >= $start && $item->$date->format('Y-m-d') < $end
                    && ! $all->contains(fn ($e) => strtoupper(trim($e->cog_no)) === strtoupper(trim($item->$ref)))) {
                    $row[$prefix.'_cog'] = $item->$ref;
                    $row[$prefix.'_date'] = $item->$date->format('d/m/Y');
                    if ($prefix === 'back') $row['back_location'] = $item->received_backload_from_location;
                    $remarks[] = $kind.': register reference only; quantity unavailable';
                }
            }
            $row['remarks'] = implode('; ', $remarks);
            $rows[] = $row;
        }
        return ['rows' => $rows, 'notes' => $notes, 'unavailable' => null];
    }
}
