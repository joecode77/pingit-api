<?php

// app/Services/CheckService.php

namespace App\Services;

use App\Mail\MonitorDegradedMail;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Mail;

class CheckService
{
    /**
     * Determine if the cooldown period has passed since the last notification.
     * Cooldown period is check_interval * threshold minutes.
     */
    private function canSendNotification(Monitor $monitor): bool
    {
        if ($monitor->last_notified_at === null) {
            return true;
        }

        $cooldownMinutes = $monitor->check_interval * $monitor->threshold;

        return $monitor->last_notified_at->addMinutes($cooldownMinutes)->isPast();
    }

    /**
     * Mark the monitor as notified.
     */
    private function markNotified(Monitor $monitor): void
    {
        $monitor->update(['last_notified_at' => now()]);
    }

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
        $previousStatus = $monitor->status;

        $monitor->consecutive_failures = 0;

        if (
            $monitor->response_time_threshold_ms !== null &&
            $responseTimeMs >= $monitor->response_time_threshold_ms
        ) {
            $newStatus = 'degraded';
        } else {
            $newStatus = 'up';
        }

        $monitor->status          = $newStatus;
        $monitor->last_checked_at = now();
        $monitor->next_check_at   = now()->addMinutes($monitor->check_interval);
        $monitor->save();

        // Close any open incident on recovery
        if ($previousStatus === 'down' && $newStatus === 'up') {
            $this->closeIncident($monitor);
            if ($this->canSendNotification($monitor)) {
                Mail::to($monitor->user->email)->send(new MonitorRecoveredMail($monitor));
                $this->markNotified($monitor);
            }
        }

        // Send degraded notification if monitor just became degraded
        if ($previousStatus !== 'degraded' && $newStatus === 'degraded') {
            if ($this->canSendNotification($monitor)) {
                Mail::to($monitor->user->email)->send(new MonitorDegradedMail($monitor));
                $this->markNotified($monitor);
            }
        }
    }

    /**
     * Handle a failed HTTP check.
     * Increments consecutive failures and marks as down if threshold is reached.
     */
    public function handleFailedCheck(Monitor $monitor): void
    {
        $previousStatus = $monitor->status;

        $monitor->consecutive_failures++;
        $monitor->last_checked_at = now();
        $monitor->next_check_at   = now()->addMinutes($monitor->check_interval);

        if (
            $monitor->consecutive_failures >= $monitor->threshold &&
            $previousStatus !== 'down'
        ) {
            $monitor->status = 'down';
            $monitor->save();

            // Open a new incident
            $this->openIncident($monitor);

            if ($this->canSendNotification($monitor)) {
                Mail::to($monitor->user->email)->send(new MonitorDownMail($monitor));
                $this->markNotified($monitor);
            }

            return;
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

    /**
     * Open a new incident for a monitor.
     */
    private function openIncident(Monitor $monitor): void
    {
        // Only open a new incident if there isn't already an open one
        $hasOpenIncident = $monitor->incidents()
            ->whereNull('ended_at')
            ->exists();

        if (! $hasOpenIncident) {
            Incident::create([
                'monitor_id' => $monitor->id,
                'started_at' => now(),
            ]);
        }
    }

    /**
     * Close the open incident for a monitor.
     */
    private function closeIncident(Monitor $monitor): void
    {
        $incident = $monitor->incidents()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if ($incident) {
            $endedAt         = now();
            $durationSeconds = abs($endedAt->diffInSeconds($incident->started_at));

            $incident->update([
                'ended_at'         => $endedAt,
                'duration_seconds' => $durationSeconds,
            ]);
        }
    }
}