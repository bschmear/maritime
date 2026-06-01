<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document request</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; }
        h1 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 12px; }
        .btn { display: inline-block; padding: 12px 22px; background: #000; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 16px; }
        .desc { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 14px; color: #374151; margin: 16px 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="card">
        @if(!empty($logoUrl))
            <img src="{{ $logoUrl }}" alt="" style="max-height: 48px; margin-bottom: 16px;">
        @endif

        @if($contactName)
            <p>Hello {{ $contactName }},</p>
        @else
            <p>Hello,</p>
        @endif

        <p><strong>{{ $senderDisplayName }}</strong> has requested a document from you.</p>

        <h1>{{ $title }}</h1>

        @if(!empty($description))
            <div class="desc">{{ $description }}</div>
        @endif

        <p>Please sign in to the customer portal and upload the requested file.</p>

        <a href="{{ $portalUrl }}" class="btn">View request in portal</a>
    </div>
</body>
</html>
