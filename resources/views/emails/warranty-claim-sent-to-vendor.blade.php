@php
    $claimRef = $claim->display_name ?? ($claim->claim_number ?? ('Claim #' . $claim->id));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty claim</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p>Hello {{ $contact->display_name ?? $contact->first_name ?? 'there' }},</p>

    <p>
        <strong>{{ $account->name ?? 'Our team' }}</strong> has shared a warranty claim
        <strong>{{ $claimRef }}</strong> with you for review.
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $reviewUrl }}" style="display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: 600;">
            View warranty claim
        </a>
    </p>

    <p>
        To approve or reject this claim in your vendor portal, sign in here:
        <a href="{{ $vendorPortalLoginUrl }}">{{ $vendorPortalLoginUrl }}</a>
    </p>

    <p style="color: #6b7280; font-size: 14px; margin-top: 32px;">
        If you did not expect this message, you can ignore it or contact the sender.
    </p>
</body>
</html>
