<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment tracking</title>
    <style>
        body { font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9fafb; }
        .container { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
        .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; padding: 28px 32px; text-align: center; }
        .content { padding: 28px 32px; }
        .button { display: inline-block; background: #2563eb; color: #fff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; }
        .meta { margin: 16px 0; padding: 16px; background: #f3f4f6; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your shipment is on the way</h1>
            @if($shipment->tracking_code)
                <p>Tracking # {{ $shipment->tracking_code }}</p>
            @endif
        </div>
        <div class="content">
            <p>Hello{{ $contact?->first_name ? ' '.$contact->first_name : '' }},</p>
            <p>{{ $account->name ?? 'We' }} created a shipment for you. Use the link below to view tracking details.</p>

            <div class="meta">
                @if($shipment->carrier)
                    <div><strong>Carrier:</strong> {{ $shipment->carrier }}</div>
                @endif
                @if($shipment->service)
                    <div><strong>Service:</strong> {{ $shipment->service }}</div>
                @endif
                @if($shipment->tracking_code)
                    <div><strong>Tracking code:</strong> {{ $shipment->tracking_code }}</div>
                @endif
            </div>

            <p style="text-align:center; margin: 28px 0;">
                <a href="{{ $trackUrl }}" class="button">Track shipment</a>
            </p>

            <p style="font-size: 14px; color: #6b7280;">If the button does not work, copy and paste this URL into your browser:<br>{{ $trackUrl }}</p>
        </div>
    </div>
</body>
</html>
