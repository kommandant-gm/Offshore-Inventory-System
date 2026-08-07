<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Asset Check-in Acknowledgement</title>
    <style>
        @page { margin: 34px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #233b25; font-size: 11px; line-height: 1.45; }
        .header { border-bottom: 3px solid #4f9f4a; padding-bottom: 14px; margin-bottom: 22px; }
        .logo { width: 235px; height: auto; }
        h1 { color: #174c20; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #174c20; font-size: 14px; border-bottom: 1px solid #cfe6c8; padding-bottom: 6px; margin: 22px 0 10px; }
        .subtitle { color: #60745d; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { border: 1px solid #d8e7d4; padding: 8px; vertical-align: top; }
        td.label { width: 27%; background: #f3f9f1; color: #60745d; font-weight: bold; }
        .ack { padding: 12px; border: 1px solid #cfe6c8; background: #f3f9f1; }
        .signature-box { border: 1px solid #aac4a4; height: 125px; margin-top: 8px; padding: 8px; }
        .signature { max-width: 100%; max-height: 110px; }
        .signed { margin-top: 8px; color: #60745d; }
        .footer { border-top: 1px solid #d8e7d4; margin-top: 28px; padding-top: 8px; color: #7f9a7a; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <img class="logo" src="{{ $logoPath }}" alt="Dayang Enterprise Sdn. Bhd.">
        <h1>Asset Check-in Acknowledgement</h1>
        <p class="subtitle">IT Team equipment receipt confirmation</p>
    </div>

    <h2>Asset details</h2>
    <table>
        <tr><td class="label">Asset tag</td><td>{{ $assignment->asset->asset_tag_no }}</td></tr>
        <tr><td class="label">Description</td><td>{{ $assignment->asset->description ?: $assignment->asset->model ?: '-' }}</td></tr>
        <tr><td class="label">Serial number</td><td>{{ $assignment->asset->serial_no ?: '-' }}</td></tr>
        <tr><td class="label">Previously assigned to</td><td>{{ $assignment->assigned_to_name }}</td></tr>
        <tr><td class="label">Employee ID</td><td>{{ $assignment->employee_id ?: '-' }}</td></tr>
        <tr><td class="label">Received by</td><td>{{ $assignment->checkin_received_by_email ?: 'muhd.isa@desb.net' }}</td></tr>
        <tr><td class="label">Received date</td><td>{{ $assignment->checkin_signed_at?->format('Y-m-d') ?: now()->format('Y-m-d') }}</td></tr>
    </table>

    <h2>Receipt acknowledgment</h2>
    <div class="ack">I acknowledge that the IT Team has received the equipment described above and confirm that this asset can be returned to the available inventory after submission.</div>

    <h2>Digital signature</h2>
    <div class="signature-box">
        @if($assignment->checkin_signature)
            <img class="signature" src="{{ $assignment->checkin_signature }}" alt="Digital signature">
        @else
            <span style="color:#7f9a7a;">Signature will appear here after the check-in form is signed.</span>
        @endif
    </div>
    <p class="signed">Acknowledged on {{ $assignment->checkin_signed_at?->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i') }}.</p>

    <div class="footer">Dayang Enterprise Sdn. Bhd. &mdash; This document was generated electronically by the Dayang Inventory Management System.</div>
</body>
</html>
