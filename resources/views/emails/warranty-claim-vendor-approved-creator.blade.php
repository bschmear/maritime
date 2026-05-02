@php
    $ref = $claim->display_name ?? ($claim->claim_number ?? ('Claim #' . $claim->id));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty claim approved</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p>Hello {{ $creator->display_name ?? $creator->name ?? 'there' }},</p>

    <p>
        The manufacturer has <strong>approved</strong> warranty claim
        <strong>{{ $ref }}</strong> for <strong>{{ $claim->vendor?->display_name ?? 'the vendor' }}</strong>.
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $internalUrl }}" style="display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: 600;">
            Open claim in {{ $account->name ?? 'Maritime' }}
        </a>
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        — {{ $account->name ?? 'Maritime' }}
    </p>
</body>
</html>
