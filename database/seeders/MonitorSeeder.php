<?php

// database/seeders/MonitorSeeder.php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\NotificationChannel;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MonitorSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@pingit.live')->first();

        // ─────────────────────────────────────────────
        // Tags
        // ─────────────────────────────────────────────

        $tags = collect([
            'production',
            'staging',
            'api',
            'frontend',
            'critical',
        ])->mapWithKeys(fn($name) => [
            $name => Tag::create(['user_id' => $user->id, 'name' => $name]),
        ]);

        // ─────────────────────────────────────────────
        // Monitor definitions — real URLs
        // ─────────────────────────────────────────────

        $monitorDefs = [
            [
                'name'                       => 'Google',
                'url'                        => 'https://google.com',
                'status'                     => 'up',
                'check_interval'             => 5,
                'threshold'                  => 3,
                'response_time_threshold_ms' => 2000,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'critical'],
                'uptime_rate'                => 0.999,
                'avg_response_ms'            => 180,
            ],
            [
                'name'                       => 'GitHub',
                'url'                        => 'https://github.com',
                'status'                     => 'up',
                'check_interval'             => 5,
                'threshold'                  => 3,
                'response_time_threshold_ms' => 2000,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'api', 'critical'],
                'uptime_rate'                => 0.998,
                'avg_response_ms'            => 220,
            ],
            [
                'name'                       => 'Cloudflare',
                'url'                        => 'https://cloudflare.com',
                'status'                     => 'up',
                'check_interval'             => 10,
                'threshold'                  => 3,
                'response_time_threshold_ms' => 1500,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'frontend'],
                'uptime_rate'                => 0.9995,
                'avg_response_ms'            => 90,
            ],
            [
                'name'                       => 'Stripe',
                'url'                        => 'https://stripe.com',
                'status'                     => 'degraded',
                'check_interval'             => 5,
                'threshold'                  => 3,
                'response_time_threshold_ms' => 800,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'api', 'critical'],
                'uptime_rate'                => 0.995,
                'avg_response_ms'            => 1100,
            ],
            [
                'name'                       => 'Vercel',
                'url'                        => 'https://vercel.com',
                'status'                     => 'down',
                'check_interval'             => 5,
                'threshold'                  => 3,
                'response_time_threshold_ms' => 2000,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 5,
                'tags'                       => ['production', 'frontend', 'critical'],
                'uptime_rate'                => 0.94,
                'avg_response_ms'            => 0,
            ],
            [
                'name'                       => 'Laravel',
                'url'                        => 'https://laravel.com',
                'status'                     => 'up',
                'check_interval'             => 15,
                'threshold'                  => 5,
                'response_time_threshold_ms' => 3000,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'frontend'],
                'uptime_rate'                => 0.97,
                'avg_response_ms'            => 350,
            ],
            [
                'name'                       => 'Tailwind CSS',
                'url'                        => 'https://tailwindcss.com',
                'status'                     => 'paused',
                'check_interval'             => 30,
                'threshold'                  => 3,
                'response_time_threshold_ms' => null,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['staging'],
                'uptime_rate'                => 0.98,
                'avg_response_ms'            => 280,
            ],
            [
                'name'                       => 'Vue.js',
                'url'                        => 'https://vuejs.org',
                'status'                     => 'up',
                'check_interval'             => 5,
                'threshold'                  => 2,
                'response_time_threshold_ms' => 1000,
                'http_method'                => 'GET',
                'follow_redirects'           => true,
                'consecutive_failures'       => 0,
                'tags'                       => ['production', 'frontend'],
                'uptime_rate'                => 0.993,
                'avg_response_ms'            => 190,
            ],
        ];

        // ─────────────────────────────────────────────
        // Create each monitor with checks + incidents
        // ─────────────────────────────────────────────

        foreach ($monitorDefs as $def) {
            $lastChecked = now()->subMinutes(rand(1, 8));

            $monitor = Monitor::create([
                'user_id'                    => $user->id,
                'name'                       => $def['name'],
                'url'                        => $def['url'],
                'status'                     => $def['status'],
                'check_interval'             => $def['check_interval'],
                'threshold'                  => $def['threshold'],
                'response_time_threshold_ms' => $def['response_time_threshold_ms'],
                'http_method'                => $def['http_method'],
                'follow_redirects'           => $def['follow_redirects'],
                'consecutive_failures'       => $def['consecutive_failures'],
                'last_checked_at'            => $def['status'] === 'paused' ? null : $lastChecked,
                'next_check_at'              => $def['status'] === 'paused'
                    ? null
                    : $lastChecked->copy()->addMinutes($def['check_interval']),
                'is_checking'                => false,
                'ssl_check_enabled'          => str_starts_with($def['url'], 'https'),
                'ssl_valid'                  => str_starts_with($def['url'], 'https') ? true : null,
                'ssl_expires_at'             => str_starts_with($def['url'], 'https')
                    ? now()->addDays(rand(30, 180))
                    : null,
                'ssl_days_remaining'         => str_starts_with($def['url'], 'https')
                    ? rand(30, 180)
                    : null,
                'ssl_alert_days_before'      => 14,
                'ssl_alert_sent'             => false,
            ]);

            // Attach tags
            foreach ($def['tags'] as $tagName) {
                if (isset($tags[$tagName])) {
                    $monitor->tags()->attach($tags[$tagName]->id);
                }
            }

            // Notification channels
            NotificationChannel::create([
                'monitor_id'         => $monitor->id,
                'type'               => 'email',
                'value'              => 'demo@pingit.live',
                'notify_on_down'     => true,
                'notify_on_recovery' => true,
                'notify_on_degraded' => true,
            ]);

            // Critical monitors get a second channel
            if (in_array('critical', $def['tags'])) {
                NotificationChannel::create([
                    'monitor_id'         => $monitor->id,
                    'type'               => 'email',
                    'value'              => 'demo@pingit.live',
                    'notify_on_down'     => true,
                    'notify_on_recovery' => false,
                    'notify_on_degraded' => false,
                ]);
            }

            // Skip checks for paused monitor
            if ($def['status'] === 'paused') {
                continue;
            }

            // ─────────────────────────────────────────────
            // Generate check history — 30 days
            // ─────────────────────────────────────────────

            $this->generateChecks($monitor, $def, 30);

            // ─────────────────────────────────────────────
            // Incidents
            // ─────────────────────────────────────────────

            $this->generateIncidents($monitor, $def);
        }
    }

    // ─────────────────────────────────────────────
    // Generate realistic check history
    // ─────────────────────────────────────────────

    private function generateChecks(Monitor $monitor, array $def, int $days): void
    {
        $checksToInsert = [];
        $intervalMins   = $def['check_interval'];
        $now            = now();
        $start          = now()->subDays($days);
        $current        = $start->copy();

        while ($current->lessThan($now)) {
            $isDown = $def['status'] === 'down' && $current->greaterThan(now()->subHours(2));

            // Random failures based on uptime rate
            $roll   = mt_rand(0, 10000) / 10000;
            $failed = $roll > $def['uptime_rate'];

            // Force failures for the 'down' monitor in the last 2 hours
            if ($isDown) {
                $failed = true;
            }

            if ($failed) {
                // Connection failure or HTTP error
                $isConnectionFailure = mt_rand(0, 1) === 1;
                $checksToInsert[]    = [
                    'monitor_id'        => $monitor->id,
                    'status_code'       => $isConnectionFailure ? 0 : $this->randomErrorCode(),
                    'response_time_ms'  => $isConnectionFailure ? null : mt_rand(3000, 30000),
                    'dns_resolution_ms' => $isConnectionFailure ? null : mt_rand(10, 80),
                    'is_up'             => false,
                    'checked_at'        => $current->toDateTimeString(),
                ];
            } else {
                // Successful check
                $baseMs = $def['avg_response_ms'];
                $jitter = mt_rand(-60, 120);
                $respMs = max(10, $baseMs + $jitter);

                // Degraded monitor gets higher response times
                if ($def['status'] === 'degraded') {
                    $respMs = mt_rand(900, 1800);
                }

                $checksToInsert[] = [
                    'monitor_id'        => $monitor->id,
                    'status_code'       => $this->randomSuccessCode(),
                    'response_time_ms'  => $respMs,
                    'dns_resolution_ms' => mt_rand(5, 60),
                    'is_up'             => true,
                    'checked_at'        => $current->toDateTimeString(),
                ];
            }

            $current->addMinutes($intervalMins);

            // Batch insert every 500 records
            if (count($checksToInsert) >= 500) {
                MonitorCheck::insert($checksToInsert);
                $checksToInsert = [];
            }
        }

        if (! empty($checksToInsert)) {
            MonitorCheck::insert($checksToInsert);
        }
    }

    // ─────────────────────────────────────────────
    // Generate realistic incidents
    // ─────────────────────────────────────────────

    private function generateIncidents(Monitor $monitor, array $def): void
    {
        // Past closed incidents — everyone gets a couple
        $pastIncidentCount = match ($def['status']) {
            'down'     => 3,
            'degraded' => 2,
            default    => rand(1, 2),
        };

        for ($i = $pastIncidentCount; $i >= 1; $i--) {
            $startedAt       = now()->subDays($i * 6)->subHours(rand(1, 12));
            $durationSeconds = rand(5 * 60, 4 * 3600); // 5 mins to 4 hours
            $endedAt         = $startedAt->copy()->addSeconds($durationSeconds);

            Incident::create([
                'monitor_id'       => $monitor->id,
                'started_at'       => $startedAt,
                'ended_at'         => $endedAt,
                'duration_seconds' => $durationSeconds,
            ]);
        }

        // Open incident for the 'down' monitor
        if ($def['status'] === 'down') {
            Incident::create([
                'monitor_id'       => $monitor->id,
                'started_at'       => now()->subHours(2)->subMinutes(15),
                'ended_at'         => null,
                'duration_seconds' => null,
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function randomSuccessCode(): int
    {
        return collect([200, 200, 200, 200, 200, 200, 200, 201, 301, 302])
            ->random();
    }

    private function randomErrorCode(): int
    {
        return collect([500, 502, 503, 504, 404, 429])
            ->random();
    }
}
