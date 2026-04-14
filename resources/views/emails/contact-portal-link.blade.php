<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer portal</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; padding: 28px 28px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; }
        .brand { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .brand img { max-height: 48px; max-width: 220px; width: auto; display: block; margin-bottom: 12px; }
        h1 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 12px; }
        .btn-row { margin: 20px 0 8px; }
        .btn { display: inline-block; padding: 12px 22px; background: #0284c7; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; margin-right: 10px; margin-bottom: 10px; }
        .btn-secondary { background: #374151; }
        ol { margin: 12px 0; padding-left: 1.25rem; color: #374151; font-size: 14px; }
        ol li { margin: 8px 0; }
        .note { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #4b5563; margin-top: 20px; }
        .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
        .platform-footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; line-height: 1.5; }
        .platform-footer a { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $senderDisplayName }}">
            @endif
        </div>

        @if($contactName)
            <p>Hello {{ $contactName }},</p>
        @else
            <p>Hello,</p>
        @endif

        <p><strong>{{ $senderDisplayName }}</strong> invited you to use our <strong>customer portal</strong>, where you can review estimates, invoices, service tickets, and shared documents in one place.</p>

        <h1>Get started</h1>
        <ol>
            <li><strong>First time?</strong> Use <strong>Create account</strong> below. Enter the <strong>same email address this message was sent to</strong> and choose a password.</li>
            <li><strong>Already set up?</strong> Use <strong>Sign in</strong> with your email and password.</li>
        </ol>

        @unless($hasCustomerProfile)
            <div class="note">
                <strong>Note:</strong> Portal registration only works if we already have you on file as a customer with this email. If you see a message that no account was found, reply to this email or call us and we will get you connected.
            </div>
        @endunless

        <div class="btn-row">
            <a class="btn" href="{{ $registerUrl }}">Create account</a>
            <a class="btn btn-secondary" href="{{ $loginUrl }}">Sign in</a>
        </div>

        <p class="footer">If the buttons do not work, copy and paste these links into your browser:</p>
        <p class="footer" style="margin-top: 8px; word-break: break-all;">Register: {{ $registerUrl }}<br><br>Sign in: {{ $loginUrl }}</p>
    </div>

    @if(!empty($platformAppName))
        <p class="platform-footer">
            @if(!empty($platformAppUrl))
                Sent by <a href="{{ $platformAppUrl }}">{{ $platformAppName }}</a>
            @else
                Sent by {{ $platformAppName }}
            @endif
        </p>
    @endif
</body>
</html>
