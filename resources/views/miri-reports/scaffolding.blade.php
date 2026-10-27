<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { margin: 0; } body { margin: 0; font-family: DejaVu Sans, sans-serif; }
table { border-collapse: collapse; table-layout: fixed; width: 100%; } td { padding: 0 1px; white-space: normal; line-height: 1.1; }
tr { page-break-inside: avoid; } thead { display: table-header-group; }
.notes { page-break-before: always; padding: 24pt; font-size: 10pt; }
</style></head><body>
@php
 $scale = min(0.59, 1190 / array_sum($layout['widths']));
 $display = fn ($value) => $value === null ? '—' : (is_numeric($value) ? rtrim(rtrim(number_format((float)$value,3,'.',','),'0'),'.') : $value);
 $style = fn ($css) => preg_replace_callback('/([\d.]+)pt/', fn ($m) => ((float)$m[1]*$scale).'pt', $css);
@endphp
<table style="width:{{ array_sum($layout['widths'])*$scale }}pt">
<colgroup>@foreach($layout['widths'] as $width)<col style="width:{{ $width*$scale }}pt">@endforeach</colgroup>
<thead>@foreach(array_slice($layout['rows'],0,6) as $row)<tr style="height:{{ $row['height']*$scale }}pt">@foreach($row['cells'] as $cell)@if($cell['span'])<td colspan="{{ $cell['span'] }}" style="{{ $style($layout['styles'][$cell['style']]) }}">{!! nl2br(e($display($cell['value']))) !!}</td>@endif @endforeach</tr>@endforeach</thead>
<tbody>@foreach(array_slice($layout['rows'],6) as $row)<tr style="height:{{ $row['height']*$scale }}pt">@foreach($row['cells'] as $cell)@if($cell['span'])<td colspan="{{ $cell['span'] }}" style="{{ $style($layout['styles'][$cell['style']]) }}">{!! nl2br(e($display($cell['value']))) !!}</td>@endif @endforeach</tr>@endforeach</tbody>
</table>
<div class="notes"><h2>Report data and notes</h2><p>Company: {{ $report['company'] }} | Generated: {{ $report['generated_at'] }}</p>
@foreach([...$report['warnings'],...$report['notes']] as $note)<p>{{ $note }}</p>@endforeach
<p>Totals on the form include only materials matched to its fixed columns. Unknown values remain unknown in totals. Labuan stock is the combined recorded yard balance, not a verified Bedaun sub-yard balance.</p>
@if($layout['unmapped'])<h3>Materials outside the template columns</h3><p>These materials are excluded from the form totals. Their full quantities are available in the Excel detail sheet.</p>@foreach($layout['unmapped'] as $column)<p>{{ $column['group'] }} / {{ $column['label'] }} ({{ $column['unit'] }})</p>@endforeach @endif
@foreach($report['unallocatedReturns'] as $return)<p>Unallocated return: {{ $return['label'] }} — {{ $return['quantity'] }} {{ $return['unit'] }}</p>@endforeach
</div></body></html>
