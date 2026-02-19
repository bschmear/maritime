<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Ticket #{{ $ticket->service_ticket_number }}</title>
    <style>
        @page {
            margin: 1in;
            size: letter;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .company-details h1 {
            color: #2563eb;
            font-size: 24px;
            margin: 0 0 5px 0;
            font-weight: bold;
        }

        .company-details p {
            margin: 2px 0;
            color: #666;
        }

        .ticket-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .ticket-number {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
        }

        .ticket-status {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .info-value {
            color: #111827;
        }

        .section {
            margin-bottom: 25px;
        }

        .section h2 {
            color: #2563eb;
            font-size: 16px;
            margin: 0 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        .service-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .service-items th,
        .service-items td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .service-items th {
            background: #f9fafb;
            font-weight: bold;
            font-size: 11px;
            color: #374151;
            text-transform: uppercase;
        }

        .service-items tbody tr:hover {
            background: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .totals {
            border-top: 2px solid #2563eb;
            padding-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

        .total-label {
            font-weight: bold;
        }

        .grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
        }

        .signature-section {
            border-top: 2px solid #2563eb;
            padding-top: 20px;
            margin-top: 30px;
        }

        .signature-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature-item {
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }

        .approved-stamp {
            background: #dcfce7;
            color: #166534;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-info">
            <div class="company-details">
                <h1>{{ $subsidiary->display_name ?? $account->name ?? 'Company Name' }}</h1>
                @if($subsidiary)
                    <p>{{ $subsidiary->address_line_1 ?? '' }}</p>
                    <p>{{ $subsidiary->city ?? '' }}, {{ $subsidiary->state ?? '' }} {{ $subsidiary->postal_code ?? '' }}</p>
                    <p>Phone: {{ $subsidiary->phone ?? '' }}</p>
                @elseif($account)
                    <p>{{ $account->name ?? '' }}</p>
                @endif
            </div>
            @if($logoUrl)
                <div class="logo">
                    <img src="{{ $logoUrl }}" alt="Company Logo" style="max-height: 80px; max-width: 200px;">
                </div>
            @endif
        </div>
    </div>

    <!-- Ticket Info -->
    <div class="ticket-info">
        <div class="ticket-header">
            <div class="ticket-number">Service Ticket #{{ $ticket->service_ticket_number }}</div>
            <div class="ticket-status">Approved & Signed</div>
        </div>

        <div class="info-grid">
            <div>
                <div class="info-item">
                    <div class="info-label">Customer</div>
                    <div class="info-value">{{ $ticket->customer->display_name }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value">{{ $ticket->location->display_name ?? '' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Asset</div>
                    <div class="info-value">{{ $ticket->assetUnit ? ($ticket->assetUnit->asset->display_name . ' - ' . $ticket->assetUnit->serial_number) : 'N/A' }}</div>
                </div>
            </div>

            <div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">{{ $ticket->created_at ? $ticket->created_at->format('M j, Y g:i A') : 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Approved</div>
                    <div class="info-value">{{ $ticket->signed_at ? $ticket->signed_at->format('M j, Y g:i A') : 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Approved By</div>
                    <div class="info-value">{{ $ticket->signed_name }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Repair Description -->
    <div class="section">
        <h2>Repair Description</h2>
        <p>{{ $ticket->repair_description }}</p>
    </div>

    <!-- Service Items -->
    <div class="section">
        <h2>Service Items</h2>
        <table class="service-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th style="width: 80px;">Qty</th>
                    <th style="width: 100px;">Rate</th>
                    <th style="width: 100px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ticket->serviceItems as $item)
                <tr>
                    <td>{{ $item->display_name }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">${{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span class="total-label">Subtotal:</span>
                <span>${{ number_format($ticket->serviceItems->sum(function($item) { return $item->quantity * $item->unit_price; }), 2) }}</span>
            </div>
            @if($ticket->tax_rate > 0)
            <div class="total-row">
                <span class="total-label">Tax ({{ number_format($ticket->tax_rate * 100, 1) }}%):</span>
                <span>${{ number_format($ticket->tax_amount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span class="total-label">Total:</span>
                <span>${{ number_format($ticket->estimated_total, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Approval Stamp -->
    <div class="approved-stamp">
        ✓ APPROVED AND SIGNED BY CUSTOMER
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <h2>Customer Authorization</h2>
        <div class="signature-info">
            <div class="signature-item">
                <div class="info-label">Signed Name</div>
                <div class="info-value">{{ $ticket->signed_name }}</div>
            </div>
            <div class="signature-item">
                <div class="info-label">Signed Date</div>
                <div class="info-value">{{ $ticket->signed_at ? $ticket->signed_at->format('M j, Y g:i A') : 'N/A' }}</div>
            </div>
        </div>

        @if($ticket->signature_file)
            <div style="margin-top: 20px; text-align: center;">
                <p><em>Customer signature on file</em></p>
            </div>
        @endif
    </div>

    <!-- Acknowledgment Text -->
    @if($account && $account->service_ticket_ack_text)
    <div class="section">
        <h2>Customer Acknowledgment</h2>
        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <p style="margin: 0; font-style: italic;">{{ $account->service_ticket_ack_text }}</p>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This service ticket was approved and signed electronically. A copy of this document has been retained for your records.</p>
        <p>Generated on {{ now()->format('M j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>