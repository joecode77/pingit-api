<?php

// app/Services/MonitorService.php

namespace App\Services;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MonitorService
{
    /**
     * Create a new monitor for the given user.
     * Automatically creates a default email notification channel for the owner.
     */
    public function create(User $user, array $data): Monitor
    {
        $monitor = $user->monitors()->create([
            'url'                        => $data['url'],
            'name'                       => $data['name'] ?? null,
            'check_interval'             => $data['check_interval'] ?? 5,
            'threshold'                  => $data['threshold'] ?? 3,
            'response_time_threshold_ms' => $data['response_time_threshold_ms'] ?? null,
            'http_method'                => $data['http_method'] ?? 'GET',
            'follow_redirects'           => $data['follow_redirects'] ?? true,
            'custom_headers'             => $data['custom_headers'] ?? null,
            'status'                     => 'pending',
            'next_check_at'              => now(),
        ]);

        // Automatically add the monitor owner's email as a default notification channel
        $monitor->notificationChannels()->create([
            'type'               => 'email',
            'value'              => $user->email,
            'notify_on_down'     => true,
            'notify_on_recovery' => true,
            'notify_on_degraded' => false,
        ]);

        return $monitor;
    }

    /**
     * Get all monitors for the given user with optional filtering, sorting, and searching.
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $user->monitors()->with('tags');

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Search by name or url
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('url', 'like', "%{$filters['search']}%");
            });
        }

        // Filter by tag
        if (! empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('name', $filters['tag']);
            });
        }

        // Sort
        $sortColumn    = in_array($filters['sort'] ?? '', ['name', 'created_at', 'last_checked_at']) ? $filters['sort'] : 'created_at';
        $sortDirection = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        return $query->get();
    }

    /**
     * Find a monitor by ID for the given user.
     * Returns null if not found or doesn't belong to the user.
     */
    public function findForUser(User $user, int $id): ?Monitor
    {
        return $user->monitors()->with('tags')->find($id);
    }

    /**
     * Update a monitor.
     */
    public function update(Monitor $monitor, array $data): Monitor
    {
        $monitor->update(array_filter($data, fn($value) => ! is_null($value)));

        return $monitor->fresh();
    }

    /**
     * Delete a monitor (soft delete).
     */
    public function delete(Monitor $monitor): void
    {
        $monitor->delete();
    }

    /**
     * Pause a monitor.
     */
    public function pause(Monitor $monitor): Monitor
    {
        $monitor->update(['status' => 'paused']);

        return $monitor->fresh();
    }

    /**
     * Resume a paused monitor.
     */
    public function resume(Monitor $monitor): Monitor
    {
        $monitor->update([
            'status'        => 'pending',
            'next_check_at' => now(),
        ]);

        return $monitor->fresh();
    }

    /**
     * Get paginated check history for a monitor.
     */
    public function getHistory(Monitor $monitor, int $perPage = 15): LengthAwarePaginator
    {
        return $monitor->checks()
            ->orderBy('checked_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get incident history for a monitor.
     */
    public function getIncidents(Monitor $monitor): \Illuminate\Database\Eloquent\Collection
    {
        return $monitor->incidents()
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get all check history for CSV export.
     */
    public function getHistoryForExport(Monitor $monitor): \Illuminate\Database\Eloquent\Collection
    {
        return $monitor->checks()
            ->orderBy('checked_at', 'desc')
            ->get();
    }

    /**
     * Get response time trends for a monitor over a given period.
     */
    public function getResponseTimeTrends(Monitor $monitor, string $period = '7d'): array
    {
        $from = match($period) {
            '24h'  => now()->subHours(24),
            '30d'  => now()->subDays(30),
            default => now()->subDays(7),
        };

        $checks = $monitor->checks()
            ->where('is_up', true)
            ->whereNotNull('response_time_ms')
            ->where('checked_at', '>=', $from)
            ->get();

        if ($checks->isEmpty()) {
            return [
                'average_ms' => null,
                'min_ms'     => null,
                'max_ms'     => null,
                'period'     => $period,
            ];
        }

        return [
            'average_ms' => round($checks->avg('response_time_ms'), 2),
            'min_ms'     => $checks->min('response_time_ms'),
            'max_ms'     => $checks->max('response_time_ms'),
            'period'     => $period,
        ];
    }

    /**
     * Get aggregated daily stats for a monitor over a given number of days.
     * Returns one entry per day with uptime counts and response time metrics.
     * Days with no checks are included with zero counts and null metrics.
     */
    public function getDailyStats(Monitor $monitor, int $days = 30): array
    {
        $days = min($days, 90);

        // Build a map of date => stats from the database in one query
        $rows = $monitor->checks()
            ->selectRaw("
                DATE(checked_at) as date,
                COUNT(*) as total_checks,
                SUM(CASE WHEN is_up = true THEN 1 ELSE 0 END) as successful_checks,
                SUM(CASE WHEN is_up = false THEN 1 ELSE 0 END) as failed_checks,
                ROUND(AVG(CASE WHEN is_up = true AND response_time_ms IS NOT NULL THEN response_time_ms END), 2) as avg_response_ms,
                MIN(CASE WHEN is_up = true AND response_time_ms IS NOT NULL THEN response_time_ms END) as min_response_ms,
                MAX(CASE WHEN is_up = true AND response_time_ms IS NOT NULL THEN response_time_ms END) as max_response_ms
            ")
            ->where('checked_at', '>=', now()->subDays($days)->startOfDay())
            ->groupByRaw('DATE(checked_at)')
            ->get()
            ->keyBy('date');

        // Build the full date range, filling gaps with zero data
        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = $rows->get($date);

            $totalChecks      = $row ? (int) $row->total_checks : 0;
            $successfulChecks = $row ? (int) $row->successful_checks : 0;
            $failedChecks     = $row ? (int) $row->failed_checks : 0;

            $result[] = [
                'date'              => $date,
                'total_checks'      => $totalChecks,
                'successful_checks' => $successfulChecks,
                'failed_checks'     => $failedChecks,
                'uptime_percentage' => $totalChecks > 0
                    ? round(($successfulChecks / $totalChecks) * 100, 2)
                    : null,
                'avg_response_ms'   => $row ? ($row->avg_response_ms !== null ? (float) $row->avg_response_ms : null) : null,
                'min_response_ms'   => $row ? ($row->min_response_ms !== null ? (int) $row->min_response_ms : null) : null,
                'max_response_ms'   => $row ? ($row->max_response_ms !== null ? (int) $row->max_response_ms : null) : null,
            ];
        }

        return $result;
    }
}
