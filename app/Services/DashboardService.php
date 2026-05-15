<?php

// app/Services/DashboardService.php

namespace App\Services;

use App\Models\User;

class DashboardService
{
    /**
     * Get summary stats for the given user.
     */
    public function getSummary(User $user): array
    {
        $total    = $user->monitors()->count();
        $up       = $user->monitors()->where('status', 'up')->count();
        $down     = $user->monitors()->where('status', 'down')->count();
        $degraded = $user->monitors()->where('status', 'degraded')->count();
        $paused   = $user->monitors()->where('status', 'paused')->count();

        // Overall uptime percentage across all monitors
        $totalChecks = $user->monitors()->withCount([
            'checks',
            'checks as successful_checks_count' => fn($q) => $q->where('is_up', true),
        ])->get();

        $totalCheckCount      = $totalChecks->sum('checks_count');
        $successfulCheckCount = $totalChecks->sum('successful_checks_count');

        $overallUptime = $totalCheckCount > 0
            ? round(($successfulCheckCount / $totalCheckCount) * 100, 2)
            : null;

        return [
            'total'                     => $total,
            'up'                        => $up,
            'down'                      => $down,
            'degraded'                  => $degraded,
            'paused'                    => $paused,
            'overall_uptime_percentage' => $overallUptime,
        ];
    }
}