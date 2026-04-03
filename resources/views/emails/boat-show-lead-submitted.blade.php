{{-- Instant staff alert: event Notify users (recipients.user_ids), else central account owner. Visitor: scheduled boat_show_event_followup. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New boat show lead</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 24px; border-radius: 10px 10px 0 0; }
        .content { background: #fff; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; }
        ul { padding-left: 20px; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">New boat show lead</h1>
        <p style="margin:8px 0 0;font-size:15px;opacity:.95;">{{ $eventName }}</p>
    </div>
    <div class="content">
        <p><strong>{{ $leadFullName }}</strong> submitted interest from the public boat show page.</p>
        <p><strong>Contact</strong><br>
            @if($leadEmail) Email: {{ $leadEmail }}<br>@endif
            @if($leadPhone) Phone: {{ $leadPhone }}<br>@endif
        </p>

        @if($leadNotes)
            <p><strong>Notes</strong><br>{{ $leadNotes }}</p>
        @endif

        <p><strong>Interested in</strong></p>
        @if(count($interestedAssets))
            <ul>
                @foreach($interestedAssets as $row)
                    <li>{{ $row['display_name'] }}</li>
                @endforeach
            </ul>
        @else
            <p class="muted">No specific assets selected.</p>
        @endif

        <p class="muted" style="margin-top:24px;">{{ $tenantLabel }}</p>
    </div>
</body>
</html>
