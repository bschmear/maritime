<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Specification sheets</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; padding: 28px 28px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; }
        .brand { margin-bottom: 20px; }
        .brand img { max-height: 48px; max-width: 220px; width: auto; }
        ul { margin: 12px 0; padding-left: 1.25rem; color: #374151; font-size: 15px; }
        ul li { margin: 8px 0; }
        a.link-spec { color: #2563eb; text-decoration: underline; }
        .btn { display: inline-block; padding: 12px 22px; background: #2563eb; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; margin-top: 8px; }
        .footer { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        @if(!empty($logoUrl))
            <div class="brand">
                <img src="{{ $logoUrl }}" alt="">
            </div>
        @endif

        <p>Hi {{ trim($greetingName ?? '') !== '' ? trim($greetingName) : 'there' }},</p>

        <p>We&rsquo;ve shared the following specification sheets for <strong>{{ $assetDisplayName }}</strong> in your customer portal. Sign in to view and print them anytime.</p>

        <ul>
            @foreach($links as $row)
                <li><a class="link-spec" href="{{ $row['url'] }}">{{ $row['label'] }}</a></li>
            @endforeach
        </ul>

        <p style="margin-top: 24px;">
            <a class="btn" href="{{ $portalUrl }}">Open customer portal</a>
        </p>

        <p class="footer">If you don&rsquo;t have an account yet, use the same email we have on file to register from the portal login page.</p>
    </div>
</body>
</html>
