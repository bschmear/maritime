<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery {{ $delivery->display_name }} — Signature Required</title>
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
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 700;
        }
        .header .delivery-number {
            font-size: 34px;
            font-weight: 800;
            font-family: 'SF Mono', Monaco, monospace;
            letter-spacing: 1px;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .body {
            padding: 32px 40px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 16px;
        }
        .summary-box {
            background: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .summary-box h3 {
            margin: 0 0 16px 0;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 500; color: #374151; }
        .detail-value { color: #111827; font-weight: 600; }
        .button-wrap {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            background: #000;
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 36px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 14px 0 rgba(0, 0, 0, 0.15);
        }
        .button:visited {
            color: #ffffff !important;
        }
        .footer {
            padding: 24px 40px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer .company-name { font-weight: 600; color: #374151; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Delivery Ready for Review</h1>
            <div class="delivery-number">{{ $delivery->display_name }}</div>
            <p>Please review and confirm receipt</p>
        </div>

        <div class="body">
            <p class="greeting">
                Dear {{ $delivery->customer?->display_name ?? 'Valued Customer' }},
            </p>

            <p>
                {{ $account->name ?? 'We' }} have scheduled a delivery for you. Please review the delivery details and sign to confirm receipt when your items have been delivered.
            </p>

            <div class="summary-box">
                <h3>Delivery Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Delivery</span>
                    <span class="detail-value">{{ $delivery->display_name }}</span>
                </div>
                @if($delivery->scheduled_at)
                <div class="detail-row">
                    <span class="detail-label">Scheduled</span>
                    <span class="detail-value">{{ $delivery->scheduled_at->format('F j, Y g:i A') }}</span>
                </div>
                @endif
                @if($delivery->address_line_1)
                <div class="detail-row">
                    <span class="detail-label">Address</span>
                    <span class="detail-value">
                        {{ $delivery->address_line_1 }}@if($delivery->city), {{ $delivery->city }}@endif
                    </span>
                </div>
                @endif
            </div>

            <div class="button-wrap">
                <a href="{{ $reviewUrl }}" class="button">Review &amp; Sign Delivery</a>
            </div>

            <p style="font-size: 14px; color: #6b7280;">
                If the button does not work, copy and paste this link into your browser:<br>
                <a href="{{ $reviewUrl }}" style="color: #059669; word-break: break-all;">{{ $reviewUrl }}</a>
            </p>
        </div>

        <div class="footer">
            <p class="company-name">{{ $account->name ?? 'Thank you' }}</p>
            <p style="margin-top: 8px;">This is an automated message. Please do not reply directly to this email unless instructed.</p>
        </div>
    </div>
</body>
</html>
