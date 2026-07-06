<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>COG Approval</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 24px; }
        .card { max-width: 900px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 24px; padding: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #475569; text-align: left; font-size: 14px; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 10px 12px; border-radius: 10px; border: 1px solid #475569; background: #0f172a; color: #fff; }
        textarea { min-height: 100px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 20px; }
        .actions { display: flex; gap: 12px; margin-top: 20px; }
        button { border: 0; border-radius: 999px; padding: 12px 20px; font-weight: 700; cursor: pointer; }
        .approve { background: #f97316; color: #fff; }
        .reject { background: #334155; color: #fff; }
        .notice { background: #172033; border: 1px solid #475569; border-radius: 14px; padding: 14px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>COG {{ $cog->cog_no }}</h1>
        <p>Please review the consignment note and acknowledge whether the goods were received correctly.</p>

        @if(session('status'))
            <div class="notice">Response recorded: {{ session('status') }}</div>
        @endif

        <div class="grid">
            <div><strong>Date</strong><br>{{ $cog->document_date?->format('Y-m-d') }}</div>
            <div><strong>Destination</strong><br>{{ $cog->destination ?? '-' }}</div>
            <div><strong>Vessel</strong><br>{{ $cog->vessel ?? '-' }}</div>
            <div><strong>Department</strong><br>{{ $cog->department ?? '-' }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Part No.</th>
                    <th>Description</th>
                    <th>S/N</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cog->items as $item)
                    <tr>
                        <td>{{ $item->qty }}</td>
                        <td>{{ $item->unit }}</td>
                        <td>{{ $item->part_no }}</td>
                        <td>{{ $item->full_description }}</td>
                        <td>{{ $item->serial_no ?: '-' }}</td>
                        <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="post" action="{{ route('cogs.approval.approve', $token) }}">
            @csrf
            <div class="grid">
                <div>
                    <label>Receiver Name</label>
                    <input type="text" name="receiver_name" value="{{ old('receiver_name', $cog->receiver_name) }}">
                </div>
                <div>
                    <label>Designation</label>
                    <input type="text" name="receiver_designation" value="{{ old('receiver_designation', $cog->receiver_designation) }}">
                </div>
            </div>
            <div style="margin-top:16px;">
                <label>Remarks</label>
                <textarea name="receiver_remarks">{{ old('receiver_remarks', $cog->receiver_remarks) }}</textarea>
            </div>
            <div class="actions">
                <button class="approve" type="submit">Approve Receipt</button>
                <button class="reject" type="submit" formaction="{{ route('cogs.approval.reject', $token) }}">Reject / Dispute</button>
            </div>
        </form>
    </div>
</body>
</html>
