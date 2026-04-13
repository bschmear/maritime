<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->display_name }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; padding: 28px 28px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; }
        .brand { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .brand img { max-height: 48px; max-width: 220px; width: auto; display: block; margin-bottom: 12px; }
        .company { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 4px; }
        .subsidiary { font-size: 15px; font-weight: 600; color: #374151; margin: 8px 0 0; }
        .location { font-size: 13px; color: #6b7280; margin: 6px 0 0; line-height: 1.45; }
        .location p { margin: 2px 0; }
        h1 { font-size: 17px; font-weight: 600; color: #111827; margin: 24px 0 12px; }
        .summary { width: 100%; border-collapse: collapse; font-size: 14px; margin: 16px 0 8px; }
        .summary th { text-align: left; font-weight: 600; color: #6b7280; padding: 8px 12px 8px 0; vertical-align: top; width: 38%; }
        .summary td { padding: 8px 0; color: #111827; }
        .amounts { margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 14px; }
        .amounts .row { display: table; width: 100%; margin: 6px 0; }
        .amounts .row > span { display: table-cell; }
        .amounts .row > span:last-child { text-align: right; font-weight: 600; }
        .amount-due { margin-top: 12px; padding-top: 12px; border-top: 1px solid #d1d5db; font-size: 16px; }
        .amount-due span:last-child { color: #0369a1; }
        .btn { display: inline-block; margin-top: 24px; padding: 12px 24px; background: #0284c7; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 13px; margin-top: 20px; line-height: 1.5; }
        .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @endif
            {{-- <p class="company">{{ $companyName }}</p> --}}
            @if(!empty($subsidiaryName))
                <p class="subsidiary">{{ $subsidiaryName }}</p>
            @endif
            @if(!empty($locationLines))
                <div class="location">
                    @foreach($locationLines as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        @if($customerName)
            <p>Hello {{ $customerName }},</p>
        @else
            <p>Hello,</p>
        @endif

        <p><strong>{{ $subsidiaryName }}</strong> has shared an invoice for your review and payment.</p>

        <h1>Invoice details</h1>
        <table class="summary" role="presentation">
            <tr>
                <th>Invoice</th>
                <td><strong>{{ $invoice->display_name }}</strong></td>
            </tr>
            @if($invoiceDate)
                <tr>
                    <th>Invoice date</th>
                    <td>{{ $invoiceDate }}</td>
                </tr>
            @endif
            @if($dueDate)
                <tr>
                    <th>Due date</th>
                    <td>{{ $dueDate }}</td>
                </tr>
            @endif
            @if($paymentTermsLabel)
                <tr>
                    <th>Payment terms</th>
                    <td>{{ $paymentTermsLabel }}</td>
                </tr>
            @endif
        </table>

        <div class="amounts">
            <div class="row">
                <span>Invoice total</span>
                <span>${{ $invoiceTotalFormatted }} {{ $currency }}</span>
            </div>
            @if((float) $invoice->amount_paid > 0)
                <div class="row">
                    <span>Amount paid</span>
                    <span>-${{ $amountPaidFormatted }} {{ $currency }}</span>
                </div>
            @endif
            <div class="row amount-due">
                <span>Amount due</span>
                <span>${{ $amountDueFormatted }} {{ $currency }}</span>
            </div>
        </div>

        <a class="btn" href="{{ $viewUrl }}">View &amp; pay invoice</a>

        <p class="muted">If the button does not work, copy this link into your browser:<br><span style="word-break: break-all;">{{ $viewUrl }}</span></p>

        <div class="footer">
            This message was sent by {{ $companyName }} regarding invoice {{ $invoice->display_name }}.
            @if(!empty($locationLines))
                <br>For questions, reply to this email or use the contact details above.
            @endif
        </div>
    </div>
</body>
</html>
