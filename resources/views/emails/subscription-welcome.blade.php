<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $workspaceName }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hi {{ $greetingName }},</p>

    <p style="margin: 0 0 16px;">
        Thanks for subscribing to <strong>{{ $planName }}</strong> for
        <strong>{{ $workspaceName }}</strong> on {{ $appName }}.
        @if($trialDays > 0)
            Your {{ $trialDays }}-day trial is active — you can explore everything before billing begins.
        @endif
    </p>

    <p style="margin: 0 0 16px;">
        Open your workspace dashboard to add locations, connect brands, set up payments, and finish onboarding.
    </p>

    <p style="margin: 0 0 24px;">
        <a href="{{ $dashboardUrl }}" style="display: inline-block; background: #0369a1; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: 600;">
            Go to your dashboard
        </a>
    </p>

    <p style="margin: 0 0 16px; font-size: 14px; color: #6b7280;">
        If the button does not work, copy and paste this link into your browser:
    </p>
    <p style="margin: 0 0 24px; word-break: break-all; font-size: 14px; color: #374151;">
        {{ $dashboardUrl }}
    </p>

    <p style="margin: 0; font-size: 13px; color: #9ca3af;">
        &copy; {{ date('Y') }} {{ $appName }}
    </p>
</body>
</html>
