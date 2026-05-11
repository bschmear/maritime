<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Request Form — {{ $opportunity->display_name }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0369a1 0%, #0c4a6e 100%);
            color: white;
            padding: 28px 36px;
            text-align: center;
        }
        .header h1 { margin: 0 0 6px 0; font-size: 20px; font-weight: 700; }
        .opp-number { font-size: 28px; font-weight: 800; font-family: monospace; }
        .body { padding: 28px 36px; }
        .cta {
            display: block;
            margin: 16px 0;
            padding: 14px 18px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            text-align: center;
        }
        .cta a {
            color: #0369a1;
            font-weight: 700;
            text-decoration: none;
        }
        .footer {
            padding: 20px 36px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Feature Request Form</h1>
            <div class="opp-number">{{ $opportunity->display_name }}</div>
        </div>
        <div class="body">
            <p>Dear {{ optional($opportunity->customer)->display_name ?? 'Valued Customer' }},</p>
            <p>
                {{ $account->name ?? 'We' }} would like your selections for <strong>{{ $assetLabel }}</strong>
                @if ($includesAddons)
                    , including optional add-ons offered on this opportunity
                @endif
                .
            </p>
            @if (! empty($customerNote))
                <div style="margin: 16px 0; padding: 14px 16px; background: #f9fafb; border-left: 4px solid #0369a1; border-radius: 6px;">
                    <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Message from your sales team</p>
                    <p style="margin: 0; font-size: 15px; color: #1f2937; white-space: pre-wrap;">{{ $customerNote }}</p>
                </div>
            @endif
            <div class="cta">
                <a href="{{ $url }}">Open Feature Request Form →</a>
            </div>
            <p style="font-size: 14px; color: #6b7280;">This link is secure and expires after some time. If you have questions, reply or contact your sales representative.</p>
        </div>
        <div class="footer">
            <p style="font-weight: 600; color: #374151;">{{ $account->name ?? 'Company' }}</p>
            <p style="margin-top: 8px;">This is an automated message.</p>
        </div>
    </div>
</body>
</html>
