<p>Hello {{ $assignment->assigned_to_name }},</p>
<p>Please review and digitally sign the checkout form for asset <strong>{{ $assignment->asset->asset_tag_no }}</strong>.</p>
<p><a href="{{ $signUrl }}">Review and sign checkout form</a></p>
<p>This link is unique to you. The checkout will be completed after you sign and submit the form.</p>
