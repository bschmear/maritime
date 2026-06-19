<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $surveyTitle }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f3f4f6; margin: 0; padding: 24px 16px; }
        .wrapper { max-width: 600px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: {{ $brandColor }}; padding: 32px; text-align: center; color: #fff; }
        .header h1 { margin: 0 0 8px; font-size: 22px; }
        .header p { margin: 0; opacity: .9; font-size: 14px; }
        .body { padding: 32px; }
        .btn { display: inline-block; background: {{ $brandColor }}; color: #fff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin-top: 16px; }
        .footer { padding: 0 32px 32px; font-size: 13px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <h1>{{ $surveyTitle }}</h1>
            @if($surveyDesc)
                <p>{{ $surveyDesc }}</p>
            @endif
        </div>
        <div class="body">
            <p>Hi{{ $recipientName ? ' '.$recipientName : '' }},</p>
            <p>{{ $tenantLabel }} would like you to complete a short survey. Your feedback helps us serve you better.</p>
            <p style="text-align:center;">
                <a href="{{ $surveyUrl }}" class="btn">Open survey</a>
            </p>
            <p style="font-size:13px;color:#6b7280;margin-top:24px;">If the button does not work, copy and paste this link into your browser:<br>{{ $surveyUrl }}</p>
        </div>
        <div class="footer">
            <p>Sent by <strong>{{ $tenantLabel }}</strong></p>
        </div>
    </div>
</div>
</body>
</html>
