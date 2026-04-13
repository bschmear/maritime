<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature Requested: Contract {{ $contract->contract_number }}</title>
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: white;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header .contract-number {
            font-size: 32px;
            font-weight: 800;
            font-family: 'SF Mono', Monaco, monospace;
            letter-spacing: 1px;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 15px;
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
            font-size: 14px;
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
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 500;
            color: #374151;
        }
        .detail-value {
            color: #111827;
            font-weight: 600;
        }
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.4);
        }
        .notice {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
            padding: 16px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 14px;
        }
        .footer {
            padding: 24px 40px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer .company-name {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Signature Requested</h1>
            <div class="contract-number">{{ $contract->contract_number }}</div>
            <p>Please review and sign your contract</p>
        </div>

        <div class="body">
            <p class="greeting">
                Dear {{ $contract->customer?->display_name ?? 'Valued Customer' }},
            </p>

            <p>
                {{ $contract->transaction?->subsidiary?->display_name ?? ($account->name ?? 'We') }} have prepared a contract for your review and signature.
                Please review the details below and sign to proceed.
            </p>

            <div class="summary-box">
                <h3>Contract Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Contract Number</span>
                    <span class="detail-value">{{ $contract->contract_number }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Date Issued</span>
                    <span class="detail-value">{{ optional($contract->created_at)->format('F j, Y') ?? '—' }}</span>
                </div>

                @if($contract->transaction?->customer_email)
                <div class="detail-row">
                    <span class="detail-label">Customer</span>
                    <span class="detail-value">{{ $contract->customer?->display_name ?? $contract->transaction->customer_email }}</span>
                </div>
                @endif
            </div>

            @if($totalAmount)
            <div class="summary-box">
                <h3>Contract Value</h3>
                <div class="total-row">
                    <span>Total</span>
                    <span>${{ number_format((float) $totalAmount, 2) }}</span>
                </div>
            </div>
            @endif

            <div class="notice">
                <strong>Action Required:</strong> This contract requires your electronic signature before work can begin.
                Click the button below to review the full contract and add your signature.
            </div>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $reviewUrl }}" class="button">
                    Review &amp; Sign Contract
                </a>
            </div>

            <p>
                If the button doesn't work, you can copy and paste this link into your browser:
            </p>
            <p style="word-break: break-all; background: #f3f4f6; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 14px;">
                {{ $reviewUrl }}
            </p>

            <p style="margin-top: 24px;">
                If you have any questions about this contract, please don't hesitate to contact us.
            </p>
        </div>

        <div class="footer">
            <p class="company-name">{{ $contract->transaction?->subsidiary?->display_name ?? ($account->name ?? 'Company') }}</p>
            @php
                $loc = $contract->transaction?->location;
            @endphp
            @if($loc)
                <p>
                    {{ $loc->address_line1 }}
                    @if($loc->address_line2), {{ $loc->address_line2 }}@endif
                    <br>
                    {{ $loc->city }}, {{ $loc->state }} {{ $loc->postal_code }}
                    @if($loc->phone)
                        <br>{{ $loc->phone }}
                    @endif
                </p>
            @endif
            <p style="margin-top: 16px;">
                This is an automated message. Please do not reply directly to this email.
            </p>
        </div>
    </div>
</body>
</html>
