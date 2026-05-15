<?php

// app/Services/MonitorService.php

namespace App\Services;

use App\Models\Monitor;
use App\Models\User;
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
     * Get all monitors for the given user.
     */
    public function getAllForUser(User $user): Collection
    {
        return $user->monitors()->orderBy('created_at', 'desc')->get();
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
     * Get paginated check history for a monitor.
     */
    public function getHistory(Monitor $monitor, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $monitor->checks()
            ->orderBy('checked_at', 'desc')
            ->paginate($perPage);
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