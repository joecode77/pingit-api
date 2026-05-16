<?php

// app/Jobs/CheckMonitorJob.php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\CheckService;
use App\Services\SslService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckMonitorJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly Monitor $monitor)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(CheckService $checkService, SslService $sslService): void
    {
        // Bail out if the monitor was deleted while the job was queued
        if ($this->monitor->trashed()) {
            return;
        }

        // Lock the monitor to prevent overlapping jobs
        $this->monitor->update(['is_checking' => true]);

        $statusCode      = 0;
        $responseTimeMs  = null;

        try {
            $startTime = microtime(true);

            $response = Http::timeout(30)
                ->withHeaders($this->monitor->custom_headers ?? [])
                ->when(! $this->monitor->follow_redirects, fn($http) => $http->withoutRedirecting())
                ->send($this->monitor->http_method, $this->monitor->url);

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode     = $response->status();

        } catch (ConnectionException $e) {
            // Connection failed or timed out
            Log::warning("Monitor [{$this->monitor->id}] connection failed: {$e->getMessage()}");
        } catch (Throwable $e) {
            // Unexpected error
            Log::error("Monitor [{$this->monitor->id}] unexpected error: {$e->getMessage()}");
        }

        // Record the check result
        $checkService->recordCheck($this->monitor, $statusCode, $responseTimeMs);

        // Update monitor status based on result
        if ($checkService->isUp($statusCode)) {
            $checkService->handleSuccessfulCheck($this->monitor, $responseTimeMs);
        } else {
            $checkService->handleFailedCheck($this->monitor);
        }

        // Check SSL certificate
        $sslService->checkSsl($this->monitor);

        // Release the lock
        $this->monitor->update(['is_checking' => false]);
    }

    /**
     * Handle a job failure.
     * Always release the is_checking lock so the monitor isn't permanently stuck.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("CheckMonitorJob failed for monitor [{$this->monitor->id}]: {$exception->getMessage()}");

        $this->monitor->update(['is_checking' => false]);
    }
}