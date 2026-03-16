<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate {{ $action === 'approved' ? 'Approved' : 'Declined' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: {{ $action === 'approved' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' }};
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
        }
        .status-badge {
            background: {{ $action === 'approved' ? '#10b981' : '#ef4444' }};
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .info-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid {{ $action === 'approved' ? '#10b981' : '#ef4444' }};
        }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; color: #374151; padding: 8px 0; width: 140px; }
        .info-value { display: table-cell; padding: 8px 0; color: #111827; }
        .button {
            display: inline-block;
            background: #0ea5e9;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .decline-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Estimate {{ $action === 'approved' ? 'Approved' : 'Declined' }}</h1>
        <p>{{ $estimate->display_name }}</p>
    </div>

    <div class="content">
        <div class="status-badge">{{ $action === 'approved' ? '✓ Approved & Signed' : '✗ Declined' }}</div>

        <p>Hello {{ $user->display_name ?? $user->first_name ?? $user->name }},</p>

        @if($action === 'approved')
        <p>The estimate has been <strong>approved and signed</strong> by the customer. Please review the details and proceed with the next steps.</p>
        @else
        <p>The estimate has been <strong>declined</strong> by the customer. Review the reason below and follow up as needed.</p>
        @endif

        <div class="info-section">
            <h3 style="margin-top: 0; color: #374151;">Estimate Details</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Estimate:</div>
                    <div class="info-value"><strong>{{ $estimate->display_name }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Customer:</div>
                    <div class="info-value">{{ $estimate->customer?->display_name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total:</div>
                    <div class="info-value"><strong>${{ number_format((float) ($estimate->primaryVersion?->total ?? 0), 2) }}</strong></div>
                </div>
                @if($action === 'approved')
                <div class="info-row">
                    <div class="info-label">Signed By:</div>
                    <div class="info-value">{{ $estimate->signed_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Signed Date:</div>
                    <div class="info-value">{{ $estimate->signed_at?->format('M j, Y \a\t g:i A') ?? 'N/A' }}</div>
                </div>
                @else
                <div class="info-row">
                    <div class="info-label">Declined At:</div>
                    <div class="info-value">{{ $estimate->declined_at?->format('M j, Y \a\t g:i A') ?? 'N/A' }}</div>
                </div>
                @endif
            </div>
        </div>

        @if($action === 'declined' && $estimate->decline_reason)
        <div class="decline-box">
            <strong>Decline Reason:</strong>
            <p style="margin: 8px 0 0;">{{ $estimate->decline_reason }}</p>
        </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ $estimateUrl }}" class="button">View Estimate</a>
        </div>

        <div class="footer">
            <p>{{ $account->name ?? 'Company Name' }} — {{ now()->format('M j, Y') }}</p>
        </div>
    </div>
</body>
</html>
