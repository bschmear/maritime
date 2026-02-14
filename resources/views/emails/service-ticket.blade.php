<x-mail::message>
# Introduction

The body of your message.

<x-mail::button :url="''">
Button Text
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>



{{-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Ticket</title>
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
            background-color: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
        }
        .content {
            background-color: #f9fafb;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
        }
        .ticket-number {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin: 10px 0;
        }
        .section {
            margin: 15px 0;
        }
        .section-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .info-row {
            margin: 5px 0;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #1e40af;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Service Ticket</h1>
        <div class="ticket-number">#{{ $serviceTicket->service_ticket_number }}</div>
    </div>

    <div class="content">
        <p>Dear {{ $serviceTicket->customer->display_name ?? 'Valued Customer' }},</p>
        
        <p>Thank you for choosing {{ $serviceTicket->subsidiary->display_name ?? 'our services' }}. Your service ticket has been created and is attached to this email.</p>

        <div class="section">
            <div class="section-title">Service Ticket Details:</div>
            <div class="info-row"><strong>Ticket Number:</strong> #{{ $serviceTicket->service_ticket_number }}</div>
            <div class="info-row"><strong>Created:</strong> {{ $serviceTicket->created_at->format('F j, Y') }}</div>
            @if($serviceTicket->assetUnit)
                <div class="info-row"><strong>Asset:</strong> {{ $serviceTicket->assetUnit->display_name }}</div>
            @endif
        </div>

        <div class="section">
            <div class="section-title">Repair Description:</div>
            <p>{{ $serviceTicket->repair_description }}</p>
        </div>

        @php
            $billableItems = $serviceTicket->serviceItems->filter(fn($item) => $item->billable !== false);
            $subtotal = $billableItems->sum(function($item) {
                $rate = floatval($item->unit_price);
                $quantity = floatval($item->quantity) ?: 1;
                $hours = floatval($item->estimated_hours) ?: 0;
                
                switch($item->billing_type) {
                    case 1: return $hours * $rate; // Hourly
                    case 2: return $rate; // Flat
                    case 3: 
                    default: return $quantity * $rate; // Quantity
                }
            });
            $taxRate = floatval($serviceTicket->tax_rate) ?: 0;
            $tax = $subtotal * ($taxRate / 100);
            $total = $subtotal + $tax;
        @endphp

        <div class="section">
            <div class="section-title">Estimated Total:</div>
            <div class="info-row"><strong>Subtotal:</strong> ${{ number_format($subtotal, 2) }}</div>
            <div class="info-row"><strong>Tax ({{ $taxRate }}%):</strong> ${{ number_format($tax, 2) }}</div>
            <div class="info-row" style="font-size: 18px; margin-top: 10px;">
                <strong>Total:</strong> <strong>${{ number_format($total, 2) }}</strong>
            </div>
        </div>

        @if($account->estimate_threshold_percent)
        <div class="section" style="background-color: #dbeafe; padding: 10px; border-left: 4px solid #1e40af;">
            <strong>Please Note:</strong> Our estimate may vary by {{ $account->estimate_threshold_percent }}%. 
            If the final cost exceeds this threshold, we will contact you for verification before proceeding with additional work.
        </div>
        @endif

        <p>Please review the attached service ticket PDF for complete details. If you have any questions or concerns, please don't hesitate to contact us.</p>
    </div>

    <div class="footer">
        <p>
            <strong>{{ $serviceTicket->subsidiary->display_name ?? 'Company Name' }}</strong><br>
            @if($serviceTicket->location)
                {{ $serviceTicket->location->address_line1 }}<br>
                @if($serviceTicket->location->address_line2)
                    {{ $serviceTicket->location->address_line2 }}<br>
                @endif
                {{ $serviceTicket->location->city }}, {{ $serviceTicket->location->state }} {{ $serviceTicket->location->postal_code }}<br>
                @if($serviceTicket->location->phone)
                    Phone: {{ $serviceTicket->location->phone }}
                @endif
            @endif
        </p>
        <p style="margin-top: 15px;">
            This is an automated message. Please do not reply directly to this email.
        </p>
    </div>
</body>
</html> --}}