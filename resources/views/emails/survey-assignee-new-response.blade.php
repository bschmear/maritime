<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New survey response</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 24px; border-radius: 10px 10px 0 0; }
        .content { background: #fff; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; }
        .btn { display: inline-block; margin-top: 16px; padding: 12px 20px; background: #2563eb; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">New survey response</h1>
        <p style="margin:8px 0 0;font-size:15px;opacity:.95;">{{ $surveyTitle }}</p>
    </div>
    <div class="content">
        <p><strong>Respondent:</strong> {{ $respondentLine }}</p>
        <p>A new response was submitted. Open it in the app to review answers and follow up.</p>
        <p><a href="{{ $responseUrl }}" class="btn">View response</a></p>
        <p class="muted" style="margin-top:24px;">If the button does not work, copy this link:<br>{{ $responseUrl }}</p>
        <p class="muted" style="margin-top:16px;">{{ $tenantLabel }}</p>
    </div>
</body>
</html>
