<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.5; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    @if(!empty($logoUrl))
        <p style="margin: 0 0 24px;">
            <img src="{{ $logoUrl }}" alt="" style="max-height: 48px; width: auto;">
        </p>
    @endif

    <p style="margin: 0 0 16px;">
        Hello{{ $customer->first_name ? ' '.$customer->first_name : '' }},
    </p>

    <p style="margin: 0 0 16px;">
        @if(count($links) > 1)
            Here are your specification sheet links:
        @else
            Here is your specification sheet link:
        @endif
    </p>

    <ul style="margin: 0 0 24px; padding-left: 20px;">
        @foreach($links as $row)
            <li style="margin-bottom: 8px;">
                <a href="{{ $row['url'] }}" style="color: #2563eb;">{{ $row['label'] }}</a>
            </li>
        @endforeach
    </ul>

    <p style="margin: 0; font-size: 14px; color: #6b7280;">
        Open the customer portal to view full details. If you have questions, reply to this email or contact us directly.
    </p>
</body>
</html>
