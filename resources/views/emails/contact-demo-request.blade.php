<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo request — {{ $fields['dealership_name'] }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .box {
            background: #fff;
            padding: 28px 32px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        h1 { font-size: 20px; margin: 0 0 20px; color: #111827; }
        dt { font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 16px; }
        dd { margin: 4px 0 0; color: #111827; }
        .message { white-space: pre-wrap; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>New demo request</h1>
        <dl>
            <dt>Name</dt>
            <dd>{{ $fields['first_name'] }} {{ $fields['last_name'] }}</dd>
            <dt>Email</dt>
            <dd><a href="mailto:{{ $fields['email'] }}">{{ $fields['email'] }}</a></dd>
            <dt>Dealership</dt>
            <dd>{{ $fields['dealership_name'] }}</dd>
            <dt>Locations</dt>
            <dd>{{ $locationsLabel }}</dd>
            @if (! empty($fields['message']))
                <dt>Message</dt>
                <dd class="message">{{ $fields['message'] }}</dd>
            @endif
        </dl>
    </div>
</body>
</html>
