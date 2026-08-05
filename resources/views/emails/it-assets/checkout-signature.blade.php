<div style="margin:0;background:#f1f7ef;padding:28px 12px;font-family:Arial,Helvetica,sans-serif;color:#234222;line-height:1.6;">
    <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #d8e7d4;border-radius:18px;overflow:hidden;">
        <div style="background:#234222;padding:24px 28px;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#b8e0ae;">Dayang Inventory System</div>
            <h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;">Asset checkout signature required</h1>
        </div>
        <div style="padding:28px;">
            <p style="margin-top:0;">Hello <strong>{{ $assignment->assigned_to_name }}</strong>,</p>
            <p>Please review the asset details below and digitally sign the checkout form.</p>

            <div style="margin:22px 0;padding:18px;background:#f8fbf7;border:1px solid #d8e7d4;border-radius:12px;">
                <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f8a6b;">Asset details</div>
                <table role="presentation" style="width:100%;margin-top:8px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#65748b;">Asset tag</td><td style="padding:5px 0;text-align:right;font-weight:bold;">{{ $assignment->asset->asset_tag_no }}</td></tr>
                    <tr><td style="padding:5px 0;color:#65748b;">Description</td><td style="padding:5px 0;text-align:right;font-weight:bold;">{{ $assignment->asset->description ?: $assignment->asset->model ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#65748b;">Serial number</td><td style="padding:5px 0;text-align:right;font-weight:bold;">{{ $assignment->asset->serial_no ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#65748b;">Checkout date</td><td style="padding:5px 0;text-align:right;font-weight:bold;">{{ $assignment->assigned_at?->format('Y-m-d') }}</td></tr>
                </table>
            </div>

            <div style="text-align:center;margin:28px 0;">
                <a href="{{ $signUrl }}" style="display:inline-block;padding:14px 24px;background:#4f9f4a;color:#ffffff;text-decoration:none;border-radius:9px;font-weight:bold;">Review and sign checkout form</a>
            </div>

            <p style="font-size:13px;color:#65748b;">This secure link is unique to you. The asset checkout will be completed after you sign and submit the form.</p>
            <p style="font-size:12px;color:#8290a8;word-break:break-all;">If the button does not work, copy and paste this link into your browser:<br>{{ $signUrl }}</p>
        </div>
        <div style="padding:16px 28px;background:#f8fafc;color:#8290a8;font-size:12px;">This is an automated message from the Dayang Inventory Management System.</div>
    </div>
</div>
