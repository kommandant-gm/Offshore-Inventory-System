<!doctype html>
<html><head><meta charset="utf-8"><style>
@page{margin:18pt 21pt}
body{font-family:DejaVu Sans,sans-serif;color:#000;font-size:10pt;line-height:1.15;margin:0}
table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{vertical-align:top;word-wrap:break-word}
.sheet{page-break-after:always}.sheet:last-child{page-break-after:auto}.code{text-align:right;margin-bottom:2pt}
.head{border:1px solid #000}.brand{text-align:center;font-weight:bold;color:#328024;font-size:20px}.company{font-size:10pt;color:#000}
.title{font-size:23px;color:#000;font-weight:bold}.logo-window{width:52px;height:54px;overflow:hidden}.logo{width:260px;margin-left:-17px;margin-top:-9px}
.header-details td{padding:2pt 4pt;font-size:10pt}.number{color:red;font-size:15pt;font-weight:bold;white-space:nowrap}.destination-value{min-height:12pt}
.header-reference{text-align:right;padding:4pt 6pt 0 0}.reference-label{font-size:9pt}.reference-block{display:inline-block;text-align:left;vertical-align:top}.document-meta{margin-top:6pt;text-align:left;font-size:10pt;line-height:14pt}
.party-fields{line-height:14pt}.party-fields .field-row{position:relative;padding-left:64pt;min-height:14pt}.party-fields .field-label{position:absolute;left:0;top:0;width:64pt;white-space:nowrap}.header-details td.aligned-field{padding:2pt 4pt;border-bottom:0}.routing-label,.routing-value{min-height:14pt;line-height:14pt}
.items th,.items td{border:1px solid #000}.items th{font-size:9pt;vertical-align:middle;height:24pt;text-align:center}
.items td{height:14pt;line-height:14pt;padding:0 2pt;font-size:10pt;font-family:DejaVu Sans Mono,monospace;white-space:nowrap}
.items .center{text-align:center}.footer td{border:1px solid #000;padding:3pt;font-size:10pt}.footer strong{display:block;min-height:18pt;font-size:9pt}.footer .caption{font-size:9pt}.footer td.totals{text-align:center;vertical-align:middle}
.signature{height:25pt;border-bottom:1px solid black;width:85%}.signature img{height:24pt;max-width:150px}.signline{border-bottom:1px solid black;width:85%;height:25pt}
.note{font-size:9pt;margin-top:3pt}.footer td.totals{font-size:11pt;line-height:15pt}
.document-remarks td{border:1px solid #000;padding:3pt;font-size:10pt;line-height:12pt}.document-remarks td+td{font-family:DejaVu Sans Mono,monospace;height:36pt}
</style></head><body>
@foreach($document['pages'] as $pageIndex=>$rows)
<div class="sheet">
<div class="code">DE-F-07E</div>
<div class="head">
<table><tr><td style="width:10%;padding:5px"><div class="logo-window"><img class="logo" src="{{ $logoPath }}"></div></td><td style="width:60%;text-align:center"><div class="brand">DAYANG ENTERPRISE SDN. BHD.</div><div class="company">NO. SYARIKAT: 198001007721 (61505-V)</div><div class="title">Internal Issue Note</div></td><td style="width:30%" class="header-reference"><div class="reference-block"><div><span class="reference-label">No : </span><span class="number">{{ $cog->display_cog_no }}</span></div><div class="document-meta">DATE: {{ $cog->document_date?->format('d/m/Y') }}<br>PAGE: {{ $pageIndex+1 }} OF {{ count($document['pages']) }}</div></div></td></tr></table>
<table class="header-details">
<tr>
<td class="aligned-field" style="width:45%"><div class="party-fields"><div class="field-row"><span class="field-label">CONSIGNEE:</span>{{ $document['header']['consignee_name'] ?: $document['header']['receiver_name'] }}</div><div class="field-row">{{ $document['header']['consignee_department'] ?: ' ' }}</div></div></td>
<td class="aligned-field" style="width:25%"><div class="routing-value">TO: {{ $document['header']['to_location'] ?: ' ' }}</div></td>
<td rowspan="2" style="width:30%;vertical-align:middle">DESTINATION: <div class="destination-value">{{ $document['header']['destination'] ?: $document['header']['to_location'] }}</div></td>
</tr>
<tr>
<td class="aligned-field"><div class="party-fields"><div class="field-row"><span class="field-label">FROM:</span>{{ $document['header']['from_department'] }}</div><div class="field-row">{{ $document['header']['from_location'] ?: ' ' }}</div></div></td>
<td class="aligned-field"><div class="routing-value">COPY TO: {{ $document['header']['copy_to'] ?: ' ' }}</div></td>
</tr>
</table>
</div>
<table class="items"><colgroup><col style="width:4%"><col style="width:5%"><col style="width:5%"><col style="width:27%"><col style="width:11%"><col style="width:17%"><col style="width:11%"><col style="width:10%"><col style="width:10%"></colgroup><thead><tr><th style="width:4%">ITEM</th><th style="width:5%">QTY</th><th style="width:5%">UNIT</th><th style="width:27%">FULL DESCRIPTION</th><th style="width:11%">SIZE / MODEL</th><th style="width:17%">TAGGING NO.</th><th style="width:11%">SERIAL NO.</th><th style="width:10%">MR #</th><th style="width:10%">REMARKS</th></tr></thead><tbody>
@foreach($rows as $row)<tr>@foreach(['item','quantity','unit','description','size_model','identifier','serial_no','mr_reference','remarks'] as $key)<td class="{{ in_array($key, ['item','quantity','unit']) ? 'center' : '' }}">{{ $row[$key] ?? '' }}</td>@endforeach</tr>@endforeach
</tbody></table>
<table class="document-remarks"><tr><td style="width:16%">Document Remarks:</td><td>@foreach($document['remarks'][$pageIndex] ?? [] as $line)<div>{{ $line ?: ' ' }}</div>@endforeach</td></tr></table>
<table class="footer"><tr><td class="caption" style="width:16%;text-align:center">TOTAL QTY</td><td class="caption" colspan="3" style="text-align:center;font-weight:bold">WE HEREBY CERTIFY THIS INFORMATION TO BE TRUE AND CORRECT</td></tr><tr><td class="totals">@if($loop->last)@if(count($document['totals'])>4)See totals section above @else @foreach($document['totals'] as $total)<div>{{ $total }}</div>@endforeach @endif @else Continued on next page @endif</td>
<td style="width:28%"><strong>Issued &amp; Checked By:</strong><div class="signline"></div>NAME: {{ $document['header']['issued_by_name'] }}<br>DESIGNATION: {{ $document['header']['issued_designation'] }}<br>DATE: {{ $cog->issued_date?->format('d/m/Y') }}</td>
<td style="width:28%"><strong>Verified By: HOD / SUPERVISOR</strong><div class="signline"></div>NAME: {{ $document['header']['verified_by_name'] }}<br>DESIGNATION: {{ $document['header']['verified_designation'] }}<br>DATE: {{ $cog->verified_date?->format('d/m/Y') }}</td>
<td style="width:28%"><strong>Received By:</strong><div class="signature">@if($loop->last && $cog->signature && preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/D',$cog->signature))<img src="{{ $cog->signature }}">@endif</div>NAME: {{ $document['header']['receiver_name'] }}<br>DESIGNATION: {{ $document['header']['receiver_designation'] }}<br>DATE: {{ $cog->received_date?->format('d/m/Y') ?: $cog->signed_at?->format('d/m/Y') }}</td></tr></table>
<div class="note"><strong>**IMPORTANT** &nbsp; COPY OF THIS ISSUE NOTE MUST BE RETURNED BACK UPON ACKNOWLEDGED RECEIPT TO ORIGINATOR</strong></div><div class="note">REV.0 · {{ $cog->movement_type }} · {{ ucfirst($cog->status) }} · Document only</div>
</div>
@endforeach

</body></html>
