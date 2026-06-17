<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery request submitted</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hi {{ $notifyUser->display_name ?? 'there' }},</p>

    <p style="margin: 0 0 16px;">
        <strong>{{ $delivery->requestedBy?->display_name ?? 'A team member' }}</strong> submitted a delivery request
        <strong>{{ $delivery->display_name }}</strong>
        @if($delivery->location)
            departing from <strong>{{ $delivery->location->display_name }}</strong>
        @endif
        @if($delivery->customer)
            for <strong>{{ $delivery->customer->display_name }}</strong>
        @endif
        .
    </p>

    @if($delivery->scheduled_at)
        <p style="margin: 0 0 16px;">Requested schedule: <strong>{{ $delivery->scheduled_at->timezone($account->timezone ?? config('app.timezone'))->format('M j, Y g:i A T') }}</strong></p>
    @endif

    <p style="margin: 0 0 24px;">
        <a href="{{ $deliveryUrl }}" style="display: inline-block; background: #000; color: #ffffff !important; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600;">Review delivery request</a>
    </p>

    <p style="margin: 0; font-size: 13px; color: #6b7280;">{{ $account->name ?? '' }}</p>
</body>
</html>
