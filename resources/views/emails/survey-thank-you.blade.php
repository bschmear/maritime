<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you — {{ $surveyTitle }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: #f3f4f6;
            margin: 0;
            padding: 24px 16px;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        /* ── Header ── */
        .header {
            background: {{ $brandColor }};
            padding: 36px 32px 28px;
            text-align: center;
        }
        .header-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,.18);
            border-radius: 50%;
            margin-bottom: 14px;
        }
        .header-icon svg {
            width: 28px;
            height: 28px;
            fill: none;
            stroke: #fff;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .header h1 {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.3px;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            color: rgba(255,255,255,.85);
        }

        /* ── Body ── */
        .body {
            padding: 32px;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            margin: 0 0 10px;
            color: #111827;
        }
        .message-text {
            font-size: 15px;
            color: #374151;
            margin: 0 0 28px;
        }

        /* ── Detail strip ── */
        .details {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .details-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 6px 0;
            font-size: 14px;
            color: #374151;
        }
        .details-row + .details-row {
            border-top: 1px solid #e5e7eb;
        }
        .details-row .label {
            color: #6b7280;
            min-width: 110px;
            flex-shrink: 0;
        }
        .details-row .value {
            color: #111827;
            font-weight: 500;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 0 0 24px;
        }

        /* ── Footer ── */
        .footer {
            padding: 0 32px 32px;
            font-size: 13px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">

        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4" />
                    <circle cx="12" cy="12" r="10" />
                </svg>
            </div>
            <h1>Response received!</h1>
            <p>{{ $surveyTitle }}</p>
        </div>

        <!-- Body -->
        <div class="body">

            <p class="greeting">Hi {{ $respondentName }},</p>
            <p class="message-text">{!! nl2br(e($body)) !!}</p>

            <!-- Detail strip -->
            <div class="details">
                <div class="details-row">
                    <span class="label">Survey</span>
                    <span class="value">{{ $surveyTitle }}</span>
                </div>
                @if($surveyDesc)
                <div class="details-row">
                    <span class="label">About</span>
                    <span class="value">{{ $surveyDesc }}</span>
                </div>
                @endif
                <div class="details-row">
                    <span class="label">Submitted</span>
                    <span class="value">{{ $submittedAt }}</span>
                </div>
                @if($answeredCount > 0)
                <div class="details-row">
                    <span class="label">Questions answered</span>
                    <span class="value">{{ $answeredCount }}</span>
                </div>
                @endif
            </div>

            <hr class="divider">

            <p class="message-text" style="margin:0;font-size:14px;color:#6b7280;">
                If you did not submit this survey, you can safely ignore this message.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin:0;">Sent by <strong>{{ $tenantLabel }}</strong></p>
        </div>

    </div>
</div>
</body>
</html>
