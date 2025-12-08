<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to join {{ $account->name }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 16px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin: 0 0 8px 0;
        }
        .subtitle {
            font-size: 16px;
            color: #6b7280;
            margin: 0;
        }
        .content {
            margin-bottom: 32px;
        }
        .invitation-details {
            background: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }
        .detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 120px;
        }
        .detail-value {
            color: #6b7280;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 14px 0 rgba(102, 126, 234, 0.4);
            transition: all 0.2s ease;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(102, 126, 234, 0.6);
        }
        .footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 14px;
            color: #9ca3af;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 16px;
            border-radius: 8px;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Maritime CRM</div>
            <h1 class="title">You're Invited!</h1>
            <p class="subtitle">{{ $inviter->name }} has invited you to join {{ $account->name }}</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>You've been invited to join <strong>{{ $account->name }}</strong> on Maritime CRM. As a <strong>{{ $role }}</strong>, you'll have access to manage and track marine sales opportunities.</p>

            <div class="invitation-details">
                <h3 style="margin: 0 0 16px 0; font-size: 18px; color: #1f2937;">Invitation Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Account:</span>
                    <span class="detail-value">{{ $account->name }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Your Role:</span>
                    <span class="detail-value">{{ $role }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Invited by:</span>
                    <span class="detail-value">{{ $inviter->name }} ({{ $inviter->email }})</span>
                </div>
            </div>

            <div class="warning">
                <strong>Important:</strong> This invitation will expire. Please accept or decline it as soon as possible.
            </div>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $invitationUrl }}" class="button">
                    Accept Invitation
                </a>
            </div>

            <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: #f3f4f6; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 14px;">
                {{ $invitationUrl }}
            </p>
        </div>

        <div class="footer">
            <p>This invitation was sent to {{ $invitation->email }}. If you didn't expect this invitation, you can safely ignore this email.</p>
            <p>&copy; {{ date('Y') }} Maritime CRM. All rights reserved.</p>
        </div>
    </div>
</body>
</html>