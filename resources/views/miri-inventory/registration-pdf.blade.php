<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Miri Inventory Registration Form</title>
<style>
@page{margin:34px 42px}body{font-family:DejaVu Sans,sans-serif;color:#233b25;font-size:11px;line-height:1.45}.header{border-bottom:3px solid #4f9f4a;padding-bottom:14px;margin-bottom:20px}.logo{width:235px}h1{color:#174c20;font-size:21px;margin:0 0 4px}.sub{color:#60745d}h2{color:#174c20;font-size:14px;border-bottom:1px solid #cfe6c8;padding-bottom:6px;margin:20px 0 9px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #d8e7d4;padding:7px;text-align:left;vertical-align:top}th{background:#f3f9f1;color:#60745d;font-size:10px}.label{width:24%;background:#f3f9f1;color:#60745d;font-weight:bold}.sign{height:78px;border:1px solid #aac4a4;padding:8px}.footer{border-top:1px solid #d8e7d4;margin-top:25px;padding-top:8px;color:#7f9a7a;font-size:9px}
</style></head><body>
<div class="header"><img class="logo" src="{{ $logoPath }}"><h1>Miri Inventory Registration Form</h1><p class="sub">Major equipment record · {{ $equipment->tag_no ?: 'No tag number' }}</p></div>
<h2>Equipment identification</h2><table>
<tr><td class="label">Category</td><td>{{ $equipment->category ?: '-' }}</td><td class="label">Status</td><td>{{ $equipment->status ?: '-' }}</td></tr>
<tr><td class="label">Section</td><td>{{ $equipment->section_1 ?: '-' }} / {{ $equipment->section_2 ?: '-' }}</td><td class="label">Unit</td><td>{{ $equipment->unit ?: '-' }}</td></tr>
<tr><td class="label">Description</td><td colspan="3">{{ $equipment->description ?: '-' }}</td></tr>
<tr><td class="label">Model / Brand</td><td>{{ $equipment->model_brand ?: '-' }}</td><td class="label">Serial No.</td><td>{{ $equipment->serial_no ?: '-' }}</td></tr>
<tr><td class="label">Tag No.</td><td>{{ $equipment->tag_no ?: '-' }}</td><td class="label">Current location</td><td>{{ $equipment->current_location ?: '-' }}</td></tr>
</table>
<h2>Movement and references</h2><table>
<tr><td class="label">Issue-out location</td><td>{{ $equipment->issue_out_location ?: '-' }}</td><td class="label">Issue-out COG</td><td>{{ $equipment->issue_out_cog_no ?: '-' }} / {{ $equipment->issue_out_cog_date?->format('Y-m-d') ?: '-' }}</td></tr>
<tr><td class="label">Received-backload COG</td><td>{{ $equipment->received_backload_cog_no ?: '-' }} / {{ $equipment->received_backload_cog_date?->format('Y-m-d') ?: '-' }}</td><td class="label">Supplier</td><td>{{ $equipment->supplier ?: '-' }}</td></tr>
<tr><td class="label">MR / Purchase order</td><td>{{ $equipment->mr_request ?: '-' }} / {{ $equipment->purchase_order ?: '-' }}</td><td class="label">Delivery order</td><td>{{ $equipment->delivery_order ?: '-' }}</td></tr></table>
<h2>Certificates</h2><table><tr><th>Certificate type</th><th>Certificate no.</th><th>Issue date</th><th>Expiry date</th></tr>@forelse($equipment->certificates as $certificate)<tr><td>{{ $certificate->certificate_type }}</td><td>{{ $certificate->certificate_no ?: '-' }}</td><td>{{ $certificate->issue_date?->format('Y-m-d') ?: '-' }}</td><td>{{ $certificate->expiry_date?->format('Y-m-d') ?: '-' }}</td></tr>@empty<tr><td colspan="4">No certificates recorded.</td></tr>@endforelse</table>
<h2>Notes</h2><table><tr><td class="label">Unfit / damage report</td><td>{{ $equipment->unfit_report ?: '-' }}</td></tr><tr><td class="label">Write-off reference</td><td>{{ $equipment->write_off_reference ?: '-' }}</td></tr><tr><td class="label">Remarks</td><td>{{ $equipment->remarks ?: '-' }}</td></tr></table>
<h2>Registration confirmation</h2><div class="sign">Registered by: ____________________________________ &nbsp;&nbsp; Date: __________________<br><br>Signature: _________________________________________</div>
<div class="footer">Dayang Enterprise Sdn. Bhd. — This document was generated electronically by the Dayang Inventory Management System.</div>
</body></html>
