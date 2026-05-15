{{-- resources/views/emails/monitor-degraded.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Degraded</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #d97706;
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }
        .body {
            padding: 32px;
        }
        .body p {
            color: #374151;
            line-height: 1.6;
            margin: 0 0 16px;
        }
        .monitor-info {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 16px;
            margin: 24px 0;
        }
        .monitor-info p {
            margin: 4px 0;
            font-size: 14px;
            color: #92400e;
        }
        .monitor-info strong {
            color: #78350f;
        }
        .footer {
            padding: 24px 32px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .footer p {
            color: #9ca3af;
            font-size: 12px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Site Degraded</h1>
        </div>
        <div class="body">
            <p>Hi {{ $monitor->user->name }},</p>
            <p>
                One of your monitored sites is responding successfully but
                <strong>slower than your configured threshold</strong>.
                This may indicate a performance issue that could lead to an outage.
            </p>
            <div class="monitor-info">
                <p><strong>Site:</strong> {{ $monitor->name ?? $monitor->url }}</p>
                <p><strong>URL:</strong> {{ $monitor->url }}</p>
                <p><strong>Response time threshold:</strong> {{ $monitor->response_time_threshold_ms }}ms</p>
                <p><strong>Detected at:</strong> {{ $monitor->last_checked_at?->toDateTimeString() ?? now()->toDateTimeString() }}</p>
            </div>
            <p>
                We recommend investigating your server performance. We will continue
                monitoring and notify you of any further changes.
            </p>
        </div>
        <div class="footer">
            <p>You are receiving this email because you registered this URL on Pingit.</p>
            <p>© {{ date('Y') }} Pingit — pingit.live</p>
        </div>
    </div>
</body>
</html>