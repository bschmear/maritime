<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate {{ $estimate->display_name }} — Approval Required</title>
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
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 700;
        }
        .header .estimate-number {
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
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0 0;
            margin-top: 8px;
            border-top: 2px solid #111827;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white !important;
            text-decoration: none;
            padding: 16px 36px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 14px 0 rgba(14, 165, 233, 0.4);
        }
        .footer {
            padding: 24px 40px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer .company-name { font-weight: 600; color: #374151; font-size: 14px; }
        .expiry-notice {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 14px 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Estimate Ready for Review</h1>
            <div class="estimate-number">{{ $estimate->display_name }}</div>
            <p>Please review and approve your estimate</p>
        </div>

        <div class="body">
            <p class="greeting">
                Dear {{ $estimate->customer?->display_name ?? 'Valued Customer' }},
            </p>

            <p>
                {{ $account->name ?? 'We' }} have prepared an estimate for your review. Please take a moment to review the details below and provide your approval or let us know if you have any questions.
            </p>

            <div class="summary-box">
                <h3>Estimate Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Estimate Number</span>
                    <span class="detail-value">{{ $estimate->display_name }}</span>
                </div>

                @if($estimate->issue_date)
                <div class="detail-row">
                    <span class="detail-label">Issue Date</span>
                    <span class="detail-value">{{ $estimate->issue_date->format('F j, Y') }}</span>
                </div>
                @endif

                @if($estimate->expiration_date)
                <div class="detail-row">
                    <span class="detail-label">Valid Until</span>
                    <span class="detail-value">{{ $estimate->expiration_date->format('F j, Y') }}</span>
                </div>
                @endif

                @if($estimate->user)
                <div class="detail-row">
                    <span class="detail-label">Sales Contact</span>
                    <span class="detail-value">{{ $estimate->user->display_name ?? $estimate->user->name }}</span>
                </div>
                @endif
            </div>

            <div class="summary-box">
                <h3>Cost Summary</h3>

                <div class="detail-row">
                    <span class="detail-label">Subtotal</span>
                    <span class="detail-value">${{ number_format($subtotal, 2) }}</span>
                </div>

                @if($taxRate > 0)
                <div class="detail-row">
                    <span class="detail-label">Tax ({{ $taxRate }}%)</span>
                    <span class="detail-value">${{ number_format($tax, 2) }}</span>
                </div>
                @endif

                <div class="total-row">
                    <span>Total</span>
                    <span>${{ number_format($total, 2) }}</span>
                </div>
            </div>

            @if($estimate->expiration_date && $estimate->expiration_date->isFuture())
            <div class="expiry-notice">
                <strong>This estimate expires on {{ $estimate->expiration_date->format('F j, Y') }}.</strong>
                Please review and respond before this date to lock in these prices.
            </div>
            @endif

            <p>Click the button below to review the full estimate and provide your signature.</p>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $reviewUrl }}" class="button">
                    Review &amp; Approve Estimate
                </a>
            </div>

            <p style="font-size: 14px; color: #6b7280;">
                If the button doesn't work, copy and paste this link into your browser:<br>
                <span style="word-break: break-all; background: #f3f4f6; padding: 6px 10px; border-radius: 4px; font-family: monospace; font-size: 13px; display: inline-block; margin-top: 6px;">{{ $reviewUrl }}</span>
            </p>
        </div>

        <div class="footer">
            <p class="company-name">{{ $account->name ?? 'Company' }}</p>
            <p style="margin-top: 12px;">This is an automated message. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
