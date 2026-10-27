<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ScaffoldingReportLayout
{
    private function target(array $column): ?string
    {
        if (! in_array(strtoupper($column['unit']), ['PC', 'PCS', 'PIECE', 'PIECES', 'UNIT', 'UNITS', 'NO', 'NOS'], true)) return null;
        $text = strtoupper($column['group'].' '.$column['label']);
        $matches = [];
        foreach (['M' => 'SINGLE.*COUPLER', 'N' => 'DOUBLE.*COUPLER', 'O' => 'SWIVEL.*COUPLER', 'P' => 'SLEEVE.*COUPLER', 'Q' => 'BEAM.*CLAMP', 'R' => 'BASE.*PLATE', 'S' => 'JOINT.*PIN'] as $cell => $pattern) {
            if (preg_match('/'.$pattern.'/', $text)) $matches[] = $cell;
        }
        if ($matches) return count($matches) === 1 ? $matches[0] : null;
        preg_match_all('/(?<![\d.])(\d+)\s*(?:FT\b|FEET\b|FOOT\b|\')/', $text, $matches);
        $sizes = array_unique($matches[1]);
        if (str_contains($text, 'TUBE') || str_contains($text, 'TUBING')) {
            if ((str_contains($text, 'ODD SIZE') && ! array_diff($sizes, ['4', '5'])) || preg_match('/4\s*[~\-]\s*5\s*FT/', $text)) return 'B';
            return count($sizes) === 1 ? ([4 => 'B', 5 => 'B', 6 => 'C', 10 => 'D', 12 => 'E', 15 => 'F', 20 => 'G'][(int) reset($sizes)] ?? null) : null;
        }
        if (count($sizes) !== 1) return null;
        $size = (int) reset($sizes);
        if (str_contains($text, 'BOARD')) return [6 => 'I', 12 => 'J'][$size] ?? null;
        if (str_contains($text, 'LADDER') && str_contains($text, 'BEAM')) return [10 => 'AE', 12 => 'AF', 20 => 'AG'][$size] ?? null;
        if (str_contains($text, 'LADDER') && (str_contains($text, 'FIBRE') || str_contains($text, 'FIBER'))) return [12 => 'AA', 20 => 'AB', 24 => 'AC'][$size] ?? null;
        if (str_contains($text, 'LADDER') && str_contains($text, 'WOOD')) return [6 => 'U', 10 => 'V', 12 => 'W', 15 => 'X', 20 => 'Y'][$size] ?? null;
        return null;
    }

    public function generate(array $report): array
    {
        $template = json_decode(file_get_contents(resource_path('report-templates/scaffolding-layout.json')), true, flags: JSON_THROW_ON_ERROR);
        $mapping = []; $unmapped = [];
        foreach ($report['columns'] as $column) {
            $target = $this->target($column);
            if ($target) $mapping[$target][] = $column['key']; else $unmapped[] = $column;
        }
        $convert = function (array $row) use ($mapping, $template) {
            $values = [];
            foreach ($template['headers'] as $column => $label) {
                $keys = $mapping[$column] ?? [];
                $values[$column] = ! $keys || collect($keys)->contains(fn ($key) => $row['values'][$key] === null)
                    ? null : array_sum(array_map(fn ($key) => (int) round($row['values'][$key] * 1000), $keys)) / 1000;
            }
            return $values;
        };
        $sum = function (array $rows) use ($template, $mapping) {
            $values = [];
            foreach ($template['headers'] as $column => $label) {
                $values[$column] = ! isset($mapping[$column]) || collect($rows)->contains(fn ($row) => $row[$column] === null)
                    ? null : array_sum(array_map(fn ($row) => (int) round($row[$column] * 1000), $rows)) / 1000;
            }
            return $values;
        };
        $title = 'SCAFFOLDING MATERIALS -  AT LOCATIONS  & AT YARDS - AS ON '.strtoupper(CarbonImmutable::parse($report['as_of'])->format('jS F Y'));
        $rows = [];
        $append = function (int $source, array $values = [], ?float $height = null) use (&$rows, $template) {
            $row = $template['rows'][(string) $source]; $row['source'] = $source; $row['number'] = count($rows) + 1;
            if ($height !== null) $row['height'] = $height;
            foreach ($row['cells'] as &$cell) {
                $cell['value'] = array_key_exists($cell['col'], $values) ? $values[$cell['col']] : ($source <= 6 ? $cell['text'] : '');
                $cell['span'] = 1;
            }
            unset($cell); $rows[] = $row;
        };
        for ($i = 1; $i <= 6; $i++) $append($i, $i === 1 ? ['A' => $title] : []);
        $projectValues = []; $yardValues = []; $previousContract = null;
        foreach ($report['rows'] as $row) {
            if ($row['kind'] === 'yard') { $yardValues[$row['label']] = $convert($row); continue; }
            if ($previousContract !== null && $previousContract !== $row['contract']) $append(12);
            $previousContract = $row['contract'];
            $values = $convert($row); $projectValues[] = $values;
            $label = ($row['contract'] ? $row['contract'].' - ' : '').$row['label'].($row['mr'] ? "\nMR: ".implode(', ', $row['mr']) : '');
            $append(7, ['A' => $label, ...$values], max(49.5, ceil(mb_strlen($label) / 38) * 12));
        }
        // Retain the original form's minimum body depth and its summary row positions.
        while (count($rows) < 51) $append(count($rows) + 1);
        $append(52);
        $locations = $sum($projectValues);
        $append(53, ['A' => 'TOTAL QUANTITY AT LOCATIONS', ...$locations]);
        $append(54); $append(55);
        $empty = array_fill_keys(array_keys($template['headers']), null);
        $labuan = $yardValues['LABUAN YARD'] ?? $empty; $bintulu = $yardValues['BINTULU YARD'] ?? $empty;
        // Do not relabel the application's combined Labuan stock as a specific sub-yard.
        $append(56, ['A' => 'LABUAN YARD STOCK', ...$labuan]);
        $append(57, ['A' => 'BINTULU YARD STOCK', ...$bintulu]);
        $append(58, ['A' => 'OVERALL TOTAL STOCK', ...$sum([$locations, $labuan, $bintulu])]); $append(59);
        $index = fn ($letters) => array_reduce(str_split($letters), fn ($n, $letter) => $n * 26 + ord($letter) - 64, 0);
        foreach ($template['merges'] as $merge) {
            preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $merge, $m);
            $row = &$rows[(int) $m[2] - 1];
            foreach ($row['cells'] as &$cell) {
                if ($cell['col'] === $m[1]) $cell['span'] = $index($m[3]) - $index($m[1]) + 1;
                elseif ($index($cell['col']) > $index($m[1]) && $index($cell['col']) <= $index($m[3])) $cell['span'] = 0;
            }
            unset($cell, $row);
        }
        return ['rows' => $rows, 'widths' => $template['widths'], 'styles' => $template['styles'], 'merges' => $template['merges'],
            'unmapped' => $unmapped, 'title' => $title, 'headers' => $template['headers']];
    }
}
