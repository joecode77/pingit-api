<?php

// app/Services/CheckService.php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;

class CheckService
{
    /**
     * Determine if a status code means the site is up.
     * 2xx and 3xx status codes are considered up.
     */
    public function isUp(int $statusCode): bool
    {
        return $statusCode >= 200 && $statusCode < 400;
    }

    /**
     * Handle a successful HTTP check.
     * Determines if the monitor is up or degraded based on response time.
     */
    public function handleSuccessfulCheck(Monitor $monitor, int $responseTimeMs): void
    {
        $monitor->consecutive_failures = 0;

        if (
            $monitor->response_time_threshold_ms !== null &&
            $responseTimeMs >= $monitor->response_time_threshold_ms
        ) {
            $monitor->status = 'degraded';
        } else {
            $monitor->status = 'up';
        }

        $monitor->last_checked_at = now();
        $monitor->next_check_at   = now()->addMinutes($monitor->check_interval);
        $monitor->save();
    }

    /**
     * Handle a failed HTTP check.
     * Increments consecutive failures and marks as down if threshold is reached.
     */
    public function handleFailedCheck(Monitor $monitor): void
    {
        $monitor->consecutive_failures++;
        $monitor->last_checked_at = now();
        $monitor->next_check_at   = now()->addMinutes($monitor->check_interval);

        if (
            $monitor->consecutive_failures >= $monitor->threshold &&
            $monitor->status !== 'down'
        ) {
            $monitor->status = 'down';
        }

        $monitor->save();
    }

    /**
     * Record a check result in the database.
     */
    public function recordCheck(Monitor $monitor, int $statusCode, ?int $responseTimeMs): MonitorCheck
    {
        return MonitorCheck::create([
            'monitor_id'       => $monitor->id,
            'status_code'      => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_up'            => $this->isUp($statusCode),
            'checked_at'       => now(),
        ]);
    }

    /**
     * Calculate the uptime percentage for a monitor.
     */
    public function uptimePercentage(Monitor $monitor): ?float
    {
        $total = $monitor->checks()->count();

        if ($total === 0) {
            return null;
        }

        $successful = $monitor->checks()->where('is_up', true)->count();

        return round(($successful / $total) * 100, 2);
    }
}