<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2>COG Approval Request</h2>
    <p>A consignment note requires receiver acknowledgement.</p>
    <p><strong>COG No:</strong> {{ $cog->cog_no }}</p>
    <p><strong>Date:</strong> {{ $cog->document_date?->format('Y-m-d') }}</p>
    <p><strong>Destination:</strong> {{ $cog->destination ?? '-' }}</p>
    <p><strong>Vessel:</strong> {{ $cog->vessel ?? '-' }}</p>
    <p><strong>Approval link expires:</strong> {{ $cog->approval_expires_at?->format('Y-m-d H:i') ?? 'On first use' }}</p>
    <p>
        <a href="{{ $approvalUrl }}" style="display:inline-block;padding:12px 18px;background:#f97316;color:#fff;text-decoration:none;border-radius:8px;">
            Review And Approve COG
        </a>
    </p>
</div>
