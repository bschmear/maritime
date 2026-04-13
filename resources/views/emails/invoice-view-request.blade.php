<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->display_name }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.6; color: #374151; max-width: 560px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        .card { background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0284c7; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 14px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <p>Hello,</p>
        <p>Your invoice <strong>{{ $invoice->display_name }}</strong> is ready.</p>
        <p>Amount due: <strong>${{ number_format((float) $invoice->amount_due, 2) }}</strong> {{ $invoice->currency }}</p>
        <a class="btn" href="{{ $viewUrl }}">View invoice</a>
        <p class="muted">If the button does not work, copy this link into your browser:<br><span style="word-break: break-all;">{{ $viewUrl }}</span></p>
    </div>
</body>
</html>
