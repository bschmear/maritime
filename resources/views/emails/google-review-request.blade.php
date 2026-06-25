<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave us a review</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; padding: 28px 28px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; }
        .brand { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .brand img { max-height: 48px; max-width: 220px; width: auto; display: block; margin-bottom: 12px; }
        h1 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 12px; }
        .btn { display: inline-block; padding: 12px 22px; background: #000; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; margin: 20px 0 8px; }
        .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; word-break: break-all; }
        .platform-footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; line-height: 1.5; }
        .platform-footer a { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $senderDisplayName }}">
            @endif
        </div>

        @if($customerName)
            <p>Hello {{ $customerName }},</p>
        @else
            <p>Hello,</p>
        @endif

        @foreach(preg_split('/\R/', $message) as $paragraph)
            @if(trim($paragraph) !== '')
                <p>{{ trim($paragraph) }}</p>
            @endif
        @endforeach

        <h1>Share your experience</h1>
        <a class="btn" href="{{ $googleReviewUrl }}">Leave a Google review</a>

        <p class="footer">If the button does not work, copy and paste this link into your browser:<br>{{ $googleReviewUrl }}</p>
    </div>

    @if(!empty($platformAppName))
        <p class="platform-footer">
            @if(!empty($platformAppUrl))
                Sent by <a href="{{ $platformAppUrl }}">{{ $platformAppName }}</a>
            @else
                Sent by {{ $platformAppName }}
            @endif
        </p>
    @endif
</body>
</html>
