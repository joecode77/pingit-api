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
     */
    public function create(User $user, array $data): Monitor
    {
        return $user->monitors()->create([
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
    }

    /**
     * Get all monitors for the given user with optional filtering, sorting, and searching.
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $user->monitors();

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
        return $user->monitors()->find($id);
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
            'status'       => 'pending',
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
}