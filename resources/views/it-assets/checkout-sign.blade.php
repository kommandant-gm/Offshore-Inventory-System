<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Asset checkout signature</title>
    <style>
        body{font-family:Arial;background:#f1f7ef;color:#234222;margin:0;padding:24px}.card{max-width:720px;margin:auto;background:white;border:1px solid #d8e7d4;border-radius:22px;padding:24px}h1{color:#174c20}table{width:100%;border-collapse:collapse;margin:20px 0}td{padding:10px;border-bottom:1px solid #e5eee3}td:last-child{font-weight:500}canvas{width:100%;height:180px;border:1px solid #aac4a4;border-radius:12px;touch-action:none}.actions{display:flex;gap:10px;margin-top:16px}button{padding:11px 18px;border:0;border-radius:999px;font-weight:bold;cursor:pointer}.submit{background:#4f9f4a;color:white}.clear{background:#edf5eb;color:#315c2e}.notice{padding:12px;border-radius:10px;background:#edf7ea;margin-bottom:16px}.policy{margin:28px 0;padding:18px;background:#f8fbf7;border:1px solid #d8e7d4;border-radius:12px}.policy h2{font-size:18px;margin:0 0 8px}.policy-intro{margin:0 0 14px;color:#526b55}.check{display:flex;gap:10px;align-items:flex-start;padding:12px 0;border-top:1px solid #e5eee3;line-height:1.5}.check input{width:18px;height:18px;flex:none;margin-top:2px;accent-color:#4f9f4a}.check a{color:#276b35;font-weight:bold}
    </style>
</head>
<body>
<div class="card">
    <img src="{{ asset('images/dayang-logo.png') }}" alt="Dayang Enterprise Sdn. Bhd." style="display:block;max-width:245px;max-height:72px;margin-bottom:18px">
    <h1>Asset Checkout Form</h1>
    <p>Please review the asset details, acknowledge each policy item, and sign below.</p>
    @if($preview ?? false)<div class="notice">This is a test preview. It will not create or change an asset checkout.</div>@endif
    <table>
        <tr><td>Asset tag</td><td>{{ $assignment->asset->asset_tag_no }}</td></tr>
        <tr><td>Description</td><td>{{ $assignment->asset->description ?: $assignment->asset->model }}</td></tr>
        <tr><td>Serial number</td><td>{{ $assignment->asset->serial_no ?: '-' }}</td></tr>
        <tr><td>Staff</td><td>{{ $assignment->assigned_to_name }}</td></tr>
        <tr><td>Employee ID</td><td>{{ $assignment->employee_id ?: '-' }}</td></tr>
        <tr><td>Department</td><td>{{ $assignment->department ?: '-' }}</td></tr>
        <tr><td>Job title</td><td>{{ $assignment->job_title ?: '-' }}</td></tr>
        <tr><td>Checkout date</td><td>{{ $assignment->assigned_at?->format('Y-m-d') }}</td></tr>
    </table>
    <form method="post" action="{{ ($preview ?? false) ? route('settings.asset-checkout-test.sign') : route('public.asset-checkout.sign', $token) }}">
        @csrf
        <section class="policy">
            <h2>Policy acknowledgment</h2>
            <p class="policy-intro">Please tick every box to confirm that you have read and understood these requirements.</p>
            @foreach($policyItems as $key => $item)
                <label class="check">
                    <input type="checkbox" name="acknowledgments[]" value="{{ $key }}" required>
                    <span>{{ $item }} @if($key === 'ict_policy') <a href="{{ $policyUrl }}" target="_blank" rel="noopener">Read the ICT Policy</a>. @endif</span>
                </label>
            @endforeach
        </section>
        <label><strong>Digital signature *</strong></label>
        <canvas id="pad" aria-label="Digital signature pad"></canvas>
        <input type="hidden" name="signature" id="signature">
        <div class="actions"><button class="clear" type="button" id="clear">Clear</button><button class="submit" type="submit">{{ ($preview ?? false) ? 'Test sign and submit' : 'Sign and submit' }}</button></div>
    </form>
</div>
<script>
const c=document.getElementById('pad'),x=c.getContext('2d'),signature=document.getElementById('signature');function size(){c.width=c.clientWidth*devicePixelRatio;c.height=c.clientHeight*devicePixelRatio;x.setTransform(devicePixelRatio,0,0,devicePixelRatio,0,0);x.lineWidth=2;x.lineCap='round'}size();let drawing=false;const point=e=>{const r=c.getBoundingClientRect();return[e.clientX-r.left,e.clientY-r.top]};c.onpointerdown=e=>{drawing=true;x.beginPath();x.moveTo(...point(e))};c.onpointermove=e=>{if(drawing){x.lineTo(...point(e));x.stroke()}};c.onpointerup=()=>drawing=false;function clearSignature(){x.setTransform(1,0,0,1,0,0);x.clearRect(0,0,c.width,c.height);x.setTransform(devicePixelRatio,0,0,devicePixelRatio,0,0);signature.value='';drawing=false}document.getElementById('clear').onclick=clearSignature;document.querySelector('form').onsubmit=()=>{signature.value=c.toDataURL('image/png');if(signature.value.length<=100){alert('Please provide your digital signature.');return false}return true};
</script>
</body>
</html>
