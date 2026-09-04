<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Miri Rental Registration Form</title>
<style>
@page{margin:34px 42px}body{font-family:DejaVu Sans,sans-serif;color:#233b25;font-size:10.5px;line-height:1.4}.header{border-bottom:3px solid #4f9f4a;padding-bottom:14px;margin-bottom:18px}.logo{width:235px}h1{color:#174c20;font-size:21px;margin:0 0 4px}.sub{color:#60745d}h2{color:#174c20;font-size:14px;border-bottom:1px solid #cfe6c8;padding-bottom:6px;margin:18px 0 8px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #d8e7d4;padding:6px;text-align:left;vertical-align:top}th{background:#f3f9f1;color:#60745d;font-size:9.5px}.label{width:25%;background:#f3f9f1;color:#60745d;font-weight:bold}.sign{height:78px;border:1px solid #aac4a4;padding:8px}.footer{border-top:1px solid #d8e7d4;margin-top:23px;padding-top:8px;color:#7f9a7a;font-size:9px}
</style></head><body>
<div class="header"><img class="logo" src="{{ $logoPath }}"><h1>Miri Rental Registration Form</h1><p class="sub">Rental record · {{ $rental->serial_tag_equipment_no ?: 'No serial/tag number' }}</p></div>
<h2>Rental item details</h2><table>
<tr><td class="label">Category</td><td>{{ $rental->category ?: '-' }}</td><td class="label">Status</td><td>{{ $rental->status ?: '-' }}</td></tr>
<tr><td class="label">Section</td><td>{{ $rental->section_1 ?: '-' }} / {{ $rental->section_2 ?: '-' }}</td><td class="label">Unit</td><td>{{ $rental->unit ?: '-' }}</td></tr>
<tr><td class="label">Description</td><td colspan="3">{{ $rental->description ?: '-' }}</td></tr>
<tr><td class="label">Serial / Tag / Equipment No.</td><td>{{ $rental->serial_tag_equipment_no ?: '-' }}</td><td class="label">Current location</td><td>{{ $rental->current_location ?: '-' }}</td></tr>
<tr><td class="label">Supplier</td><td>{{ $rental->supplier ?: '-' }}</td><td class="label">Project / Contract</td><td>{{ $rental->project_contract ?: '-' }}</td></tr>
<tr><td class="label">Rental due date</td><td>{{ $rental->rental_due_date?->format('Y-m-d') ?: '-' }}</td><td class="label">Active</td><td>{{ $rental->active ? 'Yes' : 'No' }}</td></tr></table>
<h2>Rental movements</h2><table>
<tr><td class="label">Issue-out COG</td><td>{{ $rental->issue_out_cog_no ?: '-' }} / {{ $rental->issue_out_cog_date?->format('Y-m-d') ?: '-' }}</td><td class="label">Received backload</td><td>{{ $rental->received_backload_cog_no ?: '-' }} / {{ $rental->received_backload_cog_date?->format('Y-m-d') ?: '-' }}</td></tr>
<tr><td class="label">Backload from location</td><td>{{ $rental->received_backload_from_location ?: '-' }}</td><td class="label">Return COG</td><td>{{ $rental->return_cog_no ?: '-' }} / {{ $rental->return_cog_date?->format('Y-m-d') ?: '-' }}</td></tr>
<tr><td class="label">Offhire certificate</td><td>{{ $rental->offhire_certificate_no ?: '-' }} / {{ $rental->offhire_certificate_date?->format('Y-m-d') ?: '-' }}</td><td class="label">Onhire certificate</td><td>{{ $rental->onhire_certificate_no ?: '-' }} / {{ $rental->onhire_certificate_date?->format('Y-m-d') ?: '-' }}</td></tr></table>
<h2>Commercial documents</h2><table><tr><th>MR No. / Date</th><th>PO / SR No. / Date</th><th>DO No. / Date</th></tr><tr><td>{{ $rental->mr_no ?: '-' }} / {{ $rental->mr_date?->format('Y-m-d') ?: '-' }}</td><td>{{ $rental->po_or_sr_no ?: '-' }} / {{ $rental->po_or_sr_date?->format('Y-m-d') ?: '-' }}</td><td>{{ $rental->do_no ?: '-' }} / {{ $rental->do_date?->format('Y-m-d') ?: '-' }}</td></tr></table>
<h2>Notes and confirmation</h2><table><tr><td class="label">Remarks</td><td>{{ $rental->remarks ?: '-' }}</td></tr></table><div class="sign" style="margin-top:12px">Registered by: ____________________________________ &nbsp;&nbsp; Date: __________________<br><br>Signature: _________________________________________</div>
<div class="footer">Dayang Enterprise Sdn. Bhd. — This document was generated electronically by the Dayang Inventory Management System.</div>
</body></html>
