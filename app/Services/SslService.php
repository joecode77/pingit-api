<?php

// app/Services/SslService.php

namespace App\Services;

use App\Mail\SslExpiryMail;
use App\Models\Monitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SslService
{
    /**
     * Check the SSL certificate for a monitor and update its SSL fields.
     */
    public function checkSsl(Monitor $monitor): void
    {
        if (! $monitor->ssl_check_enabled) {
            return;
        }

        // Only check HTTPS URLs
        if (! str_starts_with($monitor->url, 'https://')) {
            return;
        }

        try {
            $host    = parse_url($monitor->url, PHP_URL_HOST);
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                ],
            ]);

            $stream = stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $stream) {
                $this->markSslInvalid($monitor);
                return;
            }

            $params = stream_context_get_params($stream);
            $cert   = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

            fclose($stream);

            if (! $cert) {
                $this->markSslInvalid($monitor);
                return;
            }

            $expiresAt     = \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t']);
            $daysRemaining = (int) now()->diffInDays($expiresAt, false);

            $monitor->update([
                'ssl_valid'          => $daysRemaining > 0,
                'ssl_expires_at'     => $expiresAt,
                'ssl_days_remaining' => $daysRemaining,
            ]);

            // Send alert if expiring soon and alert not already sent
            if (
                $daysRemaining > 0 &&
                $daysRemaining <= $monitor->ssl_alert_days_before &&
                ! $monitor->ssl_alert_sent
            ) {
                Mail::to($monitor->user->email)->send(new SslExpiryMail($monitor));
                $monitor->update(['ssl_alert_sent' => true]);
            }

            // Reset alert sent flag if cert was renewed
            if ($daysRemaining > $monitor->ssl_alert_days_before && $monitor->ssl_alert_sent) {
                $monitor->update(['ssl_alert_sent' => false]);
            }

        } catch (\Throwable $e) {
            Log::warning("SSL check failed for monitor [{$monitor->id}]: {$e->getMessage()}");
            $this->markSslInvalid($monitor);
        }
    }

    /**
     * Mark the monitor's SSL as invalid.
     */
    private function markSslInvalid(Monitor $monitor): void
    {
        $monitor->update([
            'ssl_valid'          => false,
            'ssl_expires_at'     => null,
            'ssl_days_remaining' => null,
        ]);
    }
}