<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Ticket Approved — #{{ $serviceTicket->service_ticket_number }}</title>
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
            background-color: #059669;
            color: white;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header .ticket-number {
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
        .signature-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
            text-align: center;
        }
        .signature-section .label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .signature-section .signed-name {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        .signature-section .signed-date {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
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
            <h1>Approval Confirmation</h1>
            <div class="ticket-number">#{{ $serviceTicket->service_ticket_number }}</div>
            <p>Your service ticket has been approved</p>
        </div>

        <div class="body">
            <p class="greeting">
                Dear {{ $serviceTicket->customer->display_name ?? 'Valued Customer' }},
            </p>

            <p>
                Thank you for approving service ticket <strong>#{{ $serviceTicket->service_ticket_number }}</strong>
                with {{ $serviceTicket->subsidiary->display_name ?? 'us' }}.
                This email serves as your confirmation.
            </p>

            {{-- Approval Details --}}
            <div class="summary-box">
                <h3>Approval Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Ticket Number</span>
                    <span class="detail-value">#{{ $serviceTicket->service_ticket_number }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Date Created</span>
                    <span class="detail-value">{{ $serviceTicket->created_at->format('F j, Y') }}</span>
                </div>

                @if($serviceTicket->assetUnit)
                <div class="detail-row">
                    <span class="detail-label">Asset</span>
                    <span class="detail-value">{{ $serviceTicket->assetUnit->display_name }}</span>
                </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">Approved On</span>
                    <span class="detail-value">{{ $serviceTicket->signed_at?->format('F j, Y \a\t g:i A') ?? '—' }}</span>
                </div>
            </div>

            {{-- Financial Summary --}}
            <div class="summary-box">
                <h3>Estimated Cost</h3>

                <div class="detail-row">
                    <span class="detail-label">Subtotal</span>
                    <span class="detail-value">${{ number_format($subtotal, 2) }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Tax ({{ $taxRate }}%)</span>
                    <span class="detail-value">${{ number_format($tax, 2) }}</span>
                </div>

                <div class="total-row">
                    <span>Total</span>
                    <span>${{ number_format($total, 2) }}</span>
                </div>
            </div>

            @if($account->estimate_threshold_percent)
            <div class="notice">
                <strong>Please note:</strong> Our estimate may vary by {{ $account->estimate_threshold_percent }}%.
                If the final cost exceeds this threshold, we will contact you for verification before proceeding with additional work.
            </div>
            @endif

            {{-- Signature Confirmation --}}
            <div class="signature-section">
                <div class="label">Signed By</div>
                <div class="signed-name">{{ $serviceTicket->signed_name ?? '—' }}</div>
                <div class="signed-date">{{ $serviceTicket->signed_at?->format('F j, Y \a\t g:i A') ?? '' }}</div>
            </div>

            <p>
                If you have any questions about this service ticket or the work to be performed,
                please don't hesitate to contact us.
            </p>
        </div>

        <div class="footer">
            <p class="company-name">{{ $serviceTicket->subsidiary->display_name ?? 'Company' }}</p>
            @if($serviceTicket->location)
                <p>
                    {{ $serviceTicket->location->address_line1 }}
                    @if($serviceTicket->location->address_line2), {{ $serviceTicket->location->address_line2 }}@endif
                    <br>
                    {{ $serviceTicket->location->city }}, {{ $serviceTicket->location->state }} {{ $serviceTicket->location->postal_code }}
                    @if($serviceTicket->location->phone)
                        <br>{{ $serviceTicket->location->phone }}
                    @endif
                </p>
            @endif
            <p style="margin-top: 16px;">
                This is an automated confirmation. Please do not reply directly to this email.
            </p>
        </div>
    </div>
</body>
</html>
