{{-- resources/views/emails/monitor-down.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Down</title>
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
            background-color: #dc2626;
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
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 16px;
            margin: 24px 0;
        }
        .monitor-info p {
            margin: 4px 0;
            font-size: 14px;
            color: #991b1b;
        }
        .monitor-info strong {
            color: #7f1d1d;
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
            <h1>🔴 Site Down Alert</h1>
        </div>
        <div class="body">
            <p>Hi {{ $monitor->user->name }},</p>
            <p>
                We detected that one of your monitored sites is currently
                <strong>down</strong>. Here are the details:
            </p>
            <div class="monitor-info">
                <p><strong>Site:</strong> {{ $monitor->name ?? $monitor->url }}</p>
                <p><strong>URL:</strong> {{ $monitor->url }}</p>
                <p><strong>Detected at:</strong> {{ $monitor->last_checked_at?->toDateTimeString() ?? now()->toDateTimeString() }}</p>
            </div>
            <p>
                We will continue monitoring this site and notify you as soon as it recovers.
            </p>
        </div>
        <div class="footer">
            <p>You are receiving this email because you registered this URL on Pingit.</p>
            <p>© {{ date('Y') }} Pingit — pingit.live</p>
        </div>
    </div>
</body>
</html>