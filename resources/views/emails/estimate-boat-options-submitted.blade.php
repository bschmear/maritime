<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boat options submitted</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hi {{ $notifyUser->name ?? 'there' }},</p>

    <p style="margin: 0 0 16px;">
        <strong>{{ $signoff->signer_name }}</strong> submitted boat options for
        <strong>{{ $lineLabel }}</strong> (line {{ $lineNumber }}) on estimate
        <strong>{{ $estimate->display_name }}</strong>.
    </p>

    @if($selections->isNotEmpty())
        <p style="margin: 0 0 8px; font-weight: 600;">Selections</p>
        <ul style="margin: 0 0 16px; padding-left: 20px;">
            @foreach($selections as $row)
                <li>
                    {{ $row->option_name ?? 'Option' }}: {{ $row->value_label ?? '—' }}
                    @if($row->price !== null && $row->price !== '')
                        (${{ number_format((float) $row->price, 2) }})
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <p style="margin: 0 0 24px;">
        <a href="{{ $estimateUrl }}" style="display: inline-block; background: #000; color: #ffffff !important; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600;">View estimate</a>
    </p>

    <p style="margin: 0; font-size: 13px; color: #6b7280;">
        {{ $account->name ?? '' }}
    </p>
</body>
</html>
