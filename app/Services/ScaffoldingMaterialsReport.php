<?php

namespace App\Services;

use App\Models\MajorEquipment;
use Illuminate\Support\Facades\DB;

class ScaffoldingMaterialsReport
{
    private function normalized(?string $value): string { return mb_strtoupper(trim((string) $value)); }

    public function generate(int $branch, array $filters): array
    {
        $items = MajorEquipment::where('branch_id', $branch)->scaffolding()->companyFilter($filters['company'])
            ->orderBy('section_2')->orderBy('description')->orderBy('size_model')->orderBy('id')->get();
        $columns = []; $itemColumns = [];
        foreach ($items as $item) {
            $group = $this->normalized($item->section_2) ?: 'UNCLASSIFIED';
            $label = $this->normalized($item->description) ?: 'ITEM #'.$item->id;
            $size = $this->normalized($item->size_model);
            if ($size && ! str_contains($label, $size)) $label .= ' / '.$size;
            $unit = $this->normalized($item->unit) ?: 'UNIT NOT RECORDED';
            $key = hash('sha256', json_encode([$group, $label, $unit]));
            $columns[$key] ??= ['key' => $key, 'group' => $group, 'label' => $label, 'unit' => $unit, 'item_ids' => []];
            $columns[$key]['item_ids'][] = $item->id; $itemColumns[$item->id] = $key;
        }
        $columns = array_values($columns);
        usort($columns, fn ($a, $b) => [$a['group'], $a['label'], $a['unit']] <=> [$b['group'], $b['label'], $b['unit']]);
        $groups = [];
        foreach ($columns as $column) {
            $index = count($groups) - 1;
            if ($index >= 0 && $groups[$index]['label'] === $column['group']) $groups[$index]['span']++;
            else $groups[] = ['label' => $column['group'], 'span' => 1];
        }
        $movements = DB::table('scaffolding_stock_movements as m')
            ->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')
            ->where('a.branch_id', $branch)->whereIn('a.equipment_id', $items->pluck('id'))
            ->where('m.document_date', '<=', $filters['as_of'])->orderBy('m.id')
            ->get(['m.*', 'a.equipment_id', 'a.yard']);
        $yardValues = []; $opened = []; $projects = []; $unallocated = []; $warnings = [];
        foreach ($movements as $movement) {
            $id = $movement->equipment_id; $column = $itemColumns[$id];
            $quantity = (int) round((float) $movement->quantity * 1000);
            $yardValues[$movement->yard][$id] = ($yardValues[$movement->yard][$id] ?? 0) + $quantity;
            if ($movement->kind === 'opening') $opened[$movement->yard][$id] = true;
            if ($movement->kind === 'issue') {
                $location = $this->normalized($movement->destination) ?: 'LOCATION NOT RECORDED';
                $contract = $this->normalized($movement->contract);
                $key = hash('sha256', json_encode([$location, $contract]));
                $projects[$key] ??= ['kind' => 'project', 'label' => $location, 'contract' => $contract, 'mr' => [], 'values' => []];
                $projects[$key]['values'][$column] = ($projects[$key]['values'][$column] ?? 0) - $quantity;
                if (filled($movement->mr_reference)) $projects[$key]['mr'][$movement->mr_reference] = true;
            }
            // Older backloads store the receiving destination, not a reliable source project.
            // Do not guess which project's holding to reduce.
            if ($movement->kind === 'backload') $unallocated[$column] = ($unallocated[$column] ?? 0) + $quantity;
        }
        $rows = []; $unknown = 0;
        foreach (ScaffoldingStockLedger::YARDS as $yard) {
            $values = [];
            foreach ($columns as $column) {
                $complete = collect($column['item_ids'])->every(fn ($id) => isset($opened[$yard][$id]));
                $value = array_sum(array_map(fn ($id) => $yardValues[$yard][$id] ?? 0, $column['item_ids']));
                if (! $complete || $value < 0) { $values[$column['key']] = null; $unknown++; }
                else $values[$column['key']] = $value / 1000;
            }
            $rows[] = ['kind' => 'yard', 'label' => $yard.' YARD', 'contract' => '', 'mr' => [], 'values' => $values];
        }
        uasort($projects, fn ($a, $b) => [$a['contract'], $a['label']] <=> [$b['contract'], $b['label']]);
        foreach ($projects as $project) {
            foreach ($columns as $column) {
                $key = $column['key'];
                $project['values'][$key] = isset($unallocated[$key]) ? null : ($project['values'][$key] ?? 0) / 1000;
            }
            $project['mr'] = array_keys($project['mr']); $rows[] = $project;
        }
        if ($unknown) $warnings[] = "$unknown yard cells are unknown: opening stock was not verified by the selected date, or backdated movements produce a negative balance.";
        if ($unallocated) $warnings[] = 'Backloads without a reliable source project exist. Affected project cells are shown as unknown rather than assigning the return to the wrong project.';
        if ($items->contains(fn ($item) => blank($item->description) || blank($item->section_2) || blank($item->unit))) $warnings[] = 'Some materials are missing descriptions, subcategories or units. Review the register before using this report.';
        return ['title' => 'SCAFFOLDING MATERIALS — AT LOCATIONS & AT YARDS', 'as_of' => $filters['as_of'],
            'company' => $filters['company'] ?: 'All companies', 'columns' => $columns, 'groups' => $groups, 'rows' => $rows,
            'warnings' => $warnings, 'unallocatedReturns' => array_values(array_map(fn ($column) => [
                'label' => $column['label'], 'unit' => $column['unit'], 'quantity' => ($unallocated[$column['key']] ?? 0) / 1000,
            ], array_filter($columns, fn ($column) => isset($unallocated[$column['key']])))),
            'notes' => [
                'Recorded-stock report. Project quantities cover confirmed issues recorded in this system; materials already at projects before recording began are not included. They are not a verified physical stock count.',
                'As-of values use document dates on confirmed movements known when this report is generated. Later backdated entries can change an earlier report. Draft notes and legacy COG fields are excluded.',
                'Columns use current subcategory, description, size/model and unit. Identical materials are combined; different units remain separate. Company is the current register assignment.',
                '— means unknown, not zero. Zero means no quantity in the included records. Contracts identify project rows and do not separate yard stock.',
            ], 'generated_at' => now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s').' MYT'];
    }
}
