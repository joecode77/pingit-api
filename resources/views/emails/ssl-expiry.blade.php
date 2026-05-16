{{-- resources/views/emails/ssl-expiry.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL Certificate Expiring Soon</title>
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
        .days-remaining {
            font-size: 36px;
            font-weight: bold;
            color: #dc2626;
            text-align: center;
            margin: 16px 0;
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
            <h1>⚠️ SSL Certificate Expiring Soon</h1>
        </div>
        <div class="body">
            <p>Hi {{ $monitor->user->name }},</p>
            <p>
                The SSL certificate for one of your monitored sites is expiring soon.
                If it expires, visitors will see a security warning and your site may become inaccessible.
            </p>
            <div class="monitor-info">
                <p><strong>Site:</strong> {{ $monitor->name ?? $monitor->url }}</p>
                <p><strong>URL:</strong> {{ $monitor->url }}</p>
                <p><strong>Certificate expires:</strong> {{ $monitor->ssl_expires_at?->toDateString() }}</p>
            </div>
            <div class="days-remaining">
                {{ $monitor->ssl_days_remaining }} days remaining
            </div>
            <p>
                Please renew your SSL certificate as soon as possible to avoid any disruption to your site.
            </p>
        </div>
        <div class="footer">
            <p>You are receiving this email because you registered this URL on Pingit.</p>
            <p>© {{ date('Y') }} Pingit — pingit.live</p>
        </div>
    </div>
</body>
</html>