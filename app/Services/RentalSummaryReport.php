<?php

namespace App\Services;

use App\Models\MiriRentalItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class RentalSummaryReport
{
    public const COLUMNS = [
        'number' => 'No', 'description' => 'Equipment Description', 'identifier' => 'Serial No. / Equipment No. / Tag No.',
        'supplier' => 'Supplier / Vendor', 'documents' => 'SR No. / DO No.', 'received' => 'Date Received / On-hired',
        'onhire' => 'On-Hire Certificate No.', 'return_cog' => 'Return COG', 'returned' => 'Date Return / Off-hire Date',
        'offhire' => 'Offhire Certificate No.', 'remarks' => 'Remarks',
    ];

    public function isGasCylinder(MiriRentalItem $item): bool
    {
        $classification = implode(' ', [$item->category, $item->section_1, $item->section_2]);
        $description = $item->description ?? '';

        return preg_match('/\bgas\b/i', $classification)
            || preg_match('/\bgas[\s-]*cylinders?\b/i', $description)
            || (preg_match('/\bcylinders?\b/i', $classification.' '.$description)
                && preg_match('/\b(oxygen|acetylene|argon|nitrogen|helium|co2|carbon dioxide|lpg)\b/i', $classification.' '.$description));
    }

    public function options(int $branch): array
    {
        if (! Schema::hasTable('miri_rental_items')) {
            return ['projects' => [], 'locations' => []];
        }
        $items = MiriRentalItem::withoutGlobalScopes()->where('branch_id', $branch)->get()->reject(fn ($item) => $this->isGasCylinder($item));

        return ['projects' => $items->pluck('project_contract')->filter()->unique()->sort()->values(), 'locations' => $items->pluck('current_location')->filter()->unique()->sort()->values()];
    }

    public function generate(int $branch, array $filters): array
    {
        $notes = [
            'Gas cylinders are excluded, as specified on form DE-F-187A.',
            'Includes rentals whose recorded hire/return dates overlap the selected month. Missing dates remain included for review rather than assumed to mean no rental.',
            'Start uses the on-hire certificate date, or delivery date when absent. End uses the later of recorded off-hire and return dates. Import/creation timestamps are not treated as hire dates.',
            'Dates are displayed without times. Where receipt/on-hire or return/off-hire dates differ, both are shown with labels.',
            'Project, location, references and remarks are current register details, not historical snapshots. Undated records cannot establish a historical rental period.',
        ];
        if (! Schema::hasTable('miri_rental_items')) {
            return ['rows' => [], 'notes' => $notes, 'unavailable' => 'Rental reporting requires the rental register database table.'];
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'])->toDateString();
        $end = CarbonImmutable::parse($start)->addMonth()->toDateString();
        $items = MiriRentalItem::withoutGlobalScopes()->where('branch_id', $branch)
            ->when($filters['project'] !== '', fn ($q) => $q->where('project_contract', $filters['project']))
            ->when($filters['location'] !== '', fn ($q) => $q->where('current_location', $filters['location']))
            ->orderBy('description')->orderBy('id')->get();
        $rows = [];
        foreach ($items as $item) {
            if ($this->isGasCylinder($item)) {
                continue;
            }
            $from = ($item->onhire_certificate_date ?? $item->do_date)?->format('Y-m-d');
            $to = collect([$item->offhire_certificate_date?->format('Y-m-d'), $item->return_cog_date?->format('Y-m-d')])->filter()->max();
            $invalid = $from && $to && $to < $from;
            if (! $invalid && (($from && $from >= $end) || ($to && $to < $start))) {
                continue;
            }
            $number = count($rows) + 1;
            if (! $from || ! $to || $invalid) {
                $notes[] = 'Item '.$number.': '.($invalid ? 'hire/return dates conflict; review required.' : (! $from ? 'hire start date missing. ' : '').(! $to ? 'no recorded return/off-hire date.' : ''));
            }
            $rows[] = [
                'id' => $item->id, 'number' => $number, 'description' => $item->description, 'identifier' => $item->serial_tag_equipment_no,
                'supplier' => $item->supplier, 'documents' => implode("\n", array_filter([$item->po_or_sr_no ? 'SR/PO: '.$item->po_or_sr_no : null, $item->do_no ? 'DO: '.$item->do_no : null])),
                'received' => $this->dates($item->do_date, $item->onhire_certificate_date, 'Received', 'On-hire'),
                'onhire' => $item->onhire_certificate_no, 'return_cog' => $item->return_cog_no,
                'returned' => $this->dates($item->return_cog_date, $item->offhire_certificate_date, 'Return', 'Off-hire'),
                'offhire' => $item->offhire_certificate_no, 'remarks' => $item->remarks,
            ];
        }

        return ['rows' => $rows, 'notes' => $notes, 'unavailable' => null];
    }

    private function dates($first, $second, string $firstLabel, string $secondLabel): ?string
    {
        if ($first && $second && ! $first->isSameDay($second)) {
            return $firstLabel.': '.$first->format('d/m/Y')."\n".$secondLabel.': '.$second->format('d/m/Y');
        }

        return ($second ?? $first)?->format('d/m/Y');
    }
}
