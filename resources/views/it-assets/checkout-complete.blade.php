<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Asset acknowledgement</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f7ef; color: #234222; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 10vh auto; background: white; border: 1px solid #d8e7d4; border-radius: 22px; padding: 30px; text-align: center; }
        h1 { color: #347b31; }
    </style>
</head>
<body>
    <div class="card">
        @if (in_array(session('status'), ['test', 'checkin-test']))
            <h1>Test signature submitted</h1>
            <p>The signature interaction worked. No asset or assignment was changed.</p>
        @elseif (session('status') === 'checkin')
            <h1>Asset check-in acknowledged</h1>
            <p>The IT Team receipt has been recorded. The asset is now available and the supervisor has been notified.</p>
        @else
            <h1>Checkout signed successfully</h1>
            <p>Your digital signature has been recorded. The asset checkout is now complete.</p>
        @endif
        <p>You may close this page.</p>
    </div>
</body>
</html>
