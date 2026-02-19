<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Ticket Approved</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-left: 4px solid #667eea;
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
            background: #667eea;
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
        .highlight {
            background: #fef3c7;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #f59e0b;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Service Ticket Approved</h1>
        <p>A service ticket has been approved and signed by the customer</p>
    </div>

    <div class="content">
        <div class="status-badge">Approved & Signed</div>

        <p>Hello {{ $user->display_name ?? $user->first_name }},</p>

        <p>A service ticket has been approved and signed by the customer. Please review the details below and take any necessary follow-up actions.</p>

        <div class="info-section">
            <h3 style="margin-top: 0; color: #667eea;">Service Ticket Details</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Ticket Number:</div>
                    <div class="info-value"><strong>{{ $ticket->service_ticket_number }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Customer:</div>
                    <div class="info-value">{{ $ticket->customer->display_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Location:</div>
                    <div class="info-value">{{ $ticket->location->display_name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Asset:</div>
                    <div class="info-value">{{ $ticket->assetUnit ? ($ticket->assetUnit->asset->display_name . ' - ' . $ticket->assetUnit->serial_number) : 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Amount:</div>
                    <div class="info-value"><strong>${{ number_format($ticket->estimated_total, 2) }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Signed By:</div>
                    <div class="info-value">{{ $ticket->signed_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Signed Date:</div>
                    <div class="info-value">{{ $ticket->signed_at ? $ticket->signed_at->format('M j, Y \a\t g:i A') : 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="highlight">
            <strong>Action Required:</strong> Please review the attached PDF and ensure all necessary work orders, scheduling, and follow-up actions are completed.
        </div>

        <div style="text-align: center;">
            <a href="{{ $ticketUrl }}" class="button">View Service Ticket</a>
        </div>

        <p>If you have any questions or need additional information, please don't hesitate to contact us.</p>

        <div class="footer">
            <p>This notification was sent automatically when the service ticket was approved.</p>
            <p>{{ $account->name ?? 'Company Name' }} - {{ now()->format('M j, Y') }}</p>
        </div>
    </div>
</body>
</html>