<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { margin: 20px; } body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172a1b; }
h1 { font-size: 15px; } table { width: 100%; border-collapse: collapse; table-layout: fixed; } th, td { border: 1px solid #555; padding: 5px; word-wrap: break-word; } th { background: #e2efda; } td { text-align: center; } .location { width: 210px; text-align: left; } thead { display: table-header-group; } tr { page-break-inside: avoid; } .panel { page-break-before: always; } .notes { font-size: 9px; margin-bottom: 12px; } .yard { background: #f1f6ed; } small { font-size: 8px; }
</style></head><body>
@foreach($panels as $panelIndex => $columns)
<div class="{{ $panelIndex ? 'panel' : '' }}">
<h1>{{ $report['title'] }} — AS OF {{ $report['as_of'] }}</h1>
<p>Company: {{ $report['company'] }} | Generated: {{ $report['generated_at'] }} | Column panel {{ $panelIndex + 1 }} of {{ count($panels) }}</p>
<div class="notes">@foreach($report['warnings'] as $warning)<p><strong>{{ $warning }}</strong></p>@endforeach
@foreach($report['notes'] as $note)<p>{{ $note }}</p>@endforeach</div>
@if(count($columns))
@php
 $groups = []; foreach ($columns as $column) { $last = count($groups)-1; if ($last >= 0 && $groups[$last]['label'] === $column['group']) $groups[$last]['span']++; else $groups[] = ['label' => $column['group'], 'span' => 1]; }
@endphp
<table><thead><tr><th rowspan="2" class="location">PROJECTS &amp; LOCATIONS<br>Contract / MR references</th>@foreach($groups as $group)<th colspan="{{ $group['span'] }}">{{ $group['label'] }}</th>@endforeach</tr><tr>@foreach($columns as $column)<th>{{ $column['label'] }}<br><small>{{ $column['unit'] }}</small></th>@endforeach</tr></thead><tbody>
@foreach($report['rows'] as $row)<tr class="{{ $row['kind'] === 'yard' ? 'yard' : '' }}"><td class="location"><strong>{{ $row['label'] }}</strong><br>{{ $row['contract'] }}@if($row['mr'])<br><small>MR: {{ implode(', ', $row['mr']) }}</small>@endif</td>@foreach($columns as $column)<td>{{ $row['values'][$column['key']] === null ? '—' : rtrim(rtrim(number_format($row['values'][$column['key']], 3, '.', ','), '0'), '.') }}</td>@endforeach</tr>@endforeach
</tbody></table>
@else<p>No scaffolding records match this company.</p>@endif
</div>
@endforeach
@if($report['unallocatedReturns'])<h2>Returns requiring source-project review</h2>@foreach($report['unallocatedReturns'] as $return)<p>{{ $return['label'] }}: {{ $return['quantity'] }} {{ $return['unit'] }}</p>@endforeach @endif
</body></html>
