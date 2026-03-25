<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Signed</title>
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
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
            background: #10b981;
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
            border-left: 4px solid #2563eb;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #374151;
            padding: 8px 0;
            width: 140px;
        }
        .info-value {
            display: table-cell;
            padding: 8px 0;
            color: #111827;
        }
        .button {
            display: inline-block;
            background: #2563eb;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Contract Signed</h1>
        <p>A contract has been signed by the customer</p>
    </div>

    <div class="content">
        <div class="status-badge">Signed</div>

        <p>Hello {{ $user->display_name ?? $user->first_name }},</p>

        <p>Contract <strong>{{ $contract->contract_number }}</strong> has been reviewed and signed by the customer. Please review the details below.</p>

        <div class="info-section">
            <h3 style="margin-top: 0; color: #2563eb;">Contract Details</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Contract Number:</div>
                    <div class="info-value"><strong>{{ $contract->contract_number }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Customer:</div>
                    <div class="info-value">{{ $contract->customer?->display_name ?? '—' }}</div>
                </div>
                @if($contract->total_amount)
                <div class="info-row">
                    <div class="info-label">Total Amount:</div>
                    <div class="info-value"><strong>${{ number_format((float) $contract->total_amount, 2) }}</strong></div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Signed By:</div>
                    <div class="info-value">{{ $contract->signed_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Signed Date:</div>
                    <div class="info-value">{{ $contract->signed_at ? $contract->signed_at->format('M j, Y \a\t g:i A') : '—' }}</div>
                </div>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ $contractUrl }}" class="button">View Contract</a>
        </div>

        <div class="footer">
            <p>This notification was sent automatically when the contract was signed.</p>
            <p>{{ $account->name ?? 'Company Name' }} — {{ now()->format('M j, Y') }}</p>
        </div>
    </div>
</body>
</html>
