<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ScaffoldingSheet
{
    public const EDITABLE = [
        'company' => 'Company', 'category' => 'Category', 'section_2' => 'Subcategory (Note 1)',
        'description' => 'Description (Note 2)', 'unit' => 'Unit', 'current_location' => 'Stock location',
        'scaffolding_contract' => 'Contract', 'status' => 'Status',
        'unfit_report' => 'Unfit Report', 'write_off_reference' => 'Write-off Reference', 'remarks' => 'Remarks',
    ];

    public const ISSUE_FIELDS = ['yard' => 'Yard', 'destination' => 'Location / project', 'contract' => 'Contract',
        'mr_reference' => 'MR request', 'quantity' => 'Qty issued', 'balance_after' => 'Balance after issue',
        'reference' => 'COG No.', 'document_date' => 'Issue-out date'];

    public static function columns(array $options, array $categories): array
    {
        $choices = ['company' => ['DESB', 'FTSB'], 'category' => array_values(array_unique(['SCAFFOLD SET', ...$categories])),
            'section_2' => $options['subcategory'], 'description' => $options['description'],
            'current_location' => ['BINTULU', 'LABUAN'], 'scaffolding_contract' => $options['contract']];
        $columns = [];
        foreach (self::EDITABLE as $key => $label) {
            $column = ['key' => $key, 'label' => $label, 'type' => 'text'];
            if (isset($choices[$key])) $column['options'] = $choices[$key];
            if ($key === 'description') $column += ['dependsOn' => 'section_2', 'optionsByValue' => $options['descriptionsBySubcategory']];
            $columns[] = $column;
        }
        foreach (['bintulu' => 'Bintulu', 'labuan' => 'Labuan'] as $yard => $label) {
            foreach (['opening' => 'Total stock (opening A)', 'issued' => 'Total issued', 'balance' => 'Balance stock (C)'] as $field => $name) {
                $columns[] = ['key' => $yard.'_'.$field, 'label' => $label.' — '.$name, 'readonly' => true, 'type' => 'text'];
            }
        }
        for ($i = 1; $i <= 10; $i++) foreach (self::ISSUE_FIELDS as $key => $label) {
            $columns[] = ['key' => "issue_{$i}_{$key}", 'label' => "Issue {$i} — {$label}", 'readonly' => true, 'type' => 'text'];
        }
        foreach (['tag_no', 'issue_out_location', 'mr_request', 'issue_out_cog_no', 'issue_out_cog_date'] as $key) {
            $columns[] = ['key' => $key, 'label' => 'Original register — '.EquipmentSheet::FIELDS[$key], 'readonly' => true, 'type' => str_ends_with($key, '_date') ? 'date' : 'text'];
        }
        return $columns;
    }

    public static function populate($items): void
    {
        $ids = $items->modelKeys();
        $accounts = DB::table('scaffolding_stock_accounts')->whereIn('equipment_id', $ids)->get()->groupBy('equipment_id');
        $issues = DB::table('scaffolding_stock_movements as m')->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')
            ->whereIn('a.equipment_id', $ids)->where('m.kind', 'issue')->orderBy('m.id')
            ->get(['m.*', 'a.equipment_id', 'a.yard'])->groupBy('equipment_id');
        foreach ($items as $item) {
            $stock = $accounts->get($item->id, collect())->keyBy('yard');
            $history = $issues->get($item->id, collect());
            foreach (['BINTULU', 'LABUAN'] as $yard) {
                $account = $stock->get($yard);
                $prefix = strtolower($yard);
                $item->setAttribute($prefix.'_opening', $account ? (float) $account->opening_quantity : 'Unverified');
                $item->setAttribute($prefix.'_balance', $account ? (float) $account->balance : 'Unverified');
                $item->setAttribute($prefix.'_issued', $account ? $history->where('yard', $yard)->sum(fn ($row) => (int) round(-(float) $row->quantity * 1000)) / 1000 : 'Unverified');
            }
            $recent = $history->take(-10)->values();
            $item->setAttribute('scaffold_issue_count', $history->count());
            $item->setAttribute('scaffold_issues', $recent->map(fn ($row) => collect($row)->only(array_keys(self::ISSUE_FIELDS))->merge(['quantity' => -(float) $row->quantity, 'balance_after' => (float) $row->balance_after]))->all());
            foreach ($recent as $index => $row) foreach (self::ISSUE_FIELDS as $key => $label) {
                $item->setAttribute('issue_'.($index + 1).'_'.$key, $key === 'quantity' ? -(float) $row->$key : ($key === 'balance_after' ? (float) $row->$key : $row->$key));
            }
        }
    }
}
