<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature request submitted</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hi {{ $notifyUser->name ?? 'there' }},</p>

    <p style="margin: 0 0 16px;">
        <strong>{{ $submission->signer_name }}</strong> submitted a feature request for
        <strong>{{ $submission->asset_display_name ?? 'an asset' }}</strong>
        on opportunity <strong>{{ $opportunity->display_name }}</strong>.
    </p>

    @if($submission->include_addons && !empty($submission->addon_selections))
        <p style="margin: 0 0 8px; font-weight: 600;">Add-ons requested (review in the app)</p>
        <ul style="margin: 0 0 16px; padding-left: 20px;">
            @foreach($submission->addon_selections as $row)
                @php $cid = $row['catalog_addon_id'] ?? null; @endphp
                <li>
                    {{ $cid !== null ? ($addonNameMap[$cid] ?? "Add-on #{$cid}") : 'Add-on' }}
                    @if(!empty($row['quantity']))
                        × {{ $row['quantity'] }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <p style="margin: 0 0 24px;">
        <a href="{{ $opportunityUrl }}" style="display: inline-block; background: #0369a1; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600;">Open opportunity</a>
    </p>

    <p style="margin: 0; font-size: 13px; color: #6b7280;">
        {{ $account->name ?? '' }}
    </p>
</body>
</html>
