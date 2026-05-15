<?php

// app/Console/Commands/DispatchMonitorChecks.php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:dispatch')]
#[Description('Dispatch check jobs for all monitors that are due for a check')]
class DispatchMonitorChecks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $monitors = Monitor::query()
            ->whereNotIn('status', ['paused'])
            ->where('is_checking', false)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->get();

        if ($monitors->isEmpty()) {
            $this->info('No monitors due for checking.');
            return;
        }

        foreach ($monitors as $monitor) {
            CheckMonitorJob::dispatch($monitor);
        }

        $this->info("Dispatched checks for {$monitors->count()} monitor(s).");
    }
}