<?php

// tests/Feature/CheckMonitorJobTest.php

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Mock SslService and DNS resolution for all tests in this file
beforeEach(function () {
    $sslMock = Mockery::mock(SslService::class);
    $sslMock->shouldReceive('checkSsl')->andReturn(null);
    app()->instance(SslService::class, $sslMock);

    $checkServiceMock = Mockery::mock(\App\Services\CheckService::class)->makePartial();
    $checkServiceMock->shouldReceive('resolveDns')->andReturn(5);
    app()->instance(\App\Services\CheckService::class, $checkServiceMock);
});

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function makeMonitorForJob(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->create(array_merge([
        'user_id'                    => $user->id,
        'url'                        => 'https://example.com',
        'status'                     => 'pending',
        'consecutive_failures'       => 0,
        'threshold'                  => 3,
        'response_time_threshold_ms' => null,
        'http_method'                => 'GET',
        'follow_redirects'           => true,
        'custom_headers'             => null,
    ], $overrides));
}

// ─────────────────────────────────────────────
// Successful checks
// ─────────────────────────────────────────────

it('records a successful check and sets status to up', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob();

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->status)->toBe('up');
    expect($monitor->fresh()->consecutive_failures)->toBe(0);
    expect($monitor->fresh()->is_checking)->toBeFalse();

    $this->assertDatabaseHas('monitor_checks', [
        'monitor_id'  => $monitor->id,
        'status_code' => 200,
        'is_up'       => true,
    ]);
});

it('sets status to degraded when response time exceeds threshold', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    // Set a very low threshold that any real response will exceed
    $monitor = makeMonitorForJob(['response_time_threshold_ms' => 0]);

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->status)->toBe('degraded');
});

// ─────────────────────────────────────────────
// Failed checks
// ─────────────────────────────────────────────

it('records a failed check and increments consecutive failures', function () {
    Http::fake([
        'https://example.com' => Http::response('Error', 500),
    ]);

    $monitor = makeMonitorForJob();

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->consecutive_failures)->toBe(1);
    expect($monitor->fresh()->status)->not->toBe('down');

    $this->assertDatabaseHas('monitor_checks', [
        'monitor_id'  => $monitor->id,
        'status_code' => 500,
        'is_up'       => false,
    ]);
});

it('marks monitor as down when threshold is reached', function () {
    Http::fake([
        'https://example.com' => Http::response('Error', 500),
    ]);

    $monitor = makeMonitorForJob([
        'threshold'            => 3,
        'consecutive_failures' => 2,
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->status)->toBe('down');
    expect($monitor->fresh()->consecutive_failures)->toBe(3);
});

it('records status code 0 and null response time on connection failure', function () {
    Http::fake([
        'https://example.com' => fn() => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);

    $monitor = makeMonitorForJob();

    CheckMonitorJob::dispatchSync($monitor);

    $this->assertDatabaseHas('monitor_checks', [
        'monitor_id'       => $monitor->id,
        'status_code'      => 0,
        'response_time_ms' => null,
        'is_up'            => false,
    ]);
});

// ─────────────────────────────────────────────
// Recovery
// ─────────────────────────────────────────────

it('recovers from down to up on successful check', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob([
        'status'               => 'down',
        'consecutive_failures' => 3,
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->status)->toBe('up');
    expect($monitor->fresh()->consecutive_failures)->toBe(0);
});

// ─────────────────────────────────────────────
// Scheduling
// ─────────────────────────────────────────────

it('updates next_check_at after a check', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob(['check_interval' => 5]);

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->next_check_at)->not->toBeNull();
    expect($monitor->fresh()->last_checked_at)->not->toBeNull();
});

it('releases is_checking lock after a check', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob();

    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->is_checking)->toBeFalse();
});

// ─────────────────────────────────────────────
// Edge cases
// ─────────────────────────────────────────────

it('does nothing if monitor was deleted before job runs', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob();
    $monitorId = $monitor->id;

    // Soft delete the monitor before the job runs
    $monitor->deleteQuietly();

    CheckMonitorJob::dispatchSync($monitor);

    // No check should have been recorded
    $this->assertDatabaseMissing('monitor_checks', [
        'monitor_id' => $monitorId,
    ]);
});

// ─────────────────────────────────────────────
// Incident grouping
// ─────────────────────────────────────────────

it('opens an incident when monitor goes down', function () {
    Http::fake([
        'https://example.com' => Http::response('Error', 500),
    ]);

    $monitor = makeMonitorForJob([
        'threshold'            => 3,
        'consecutive_failures' => 2,
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    $this->assertDatabaseHas('incidents', [
        'monitor_id' => $monitor->id,
        'ended_at'   => null,
    ]);
});

it('does not open a second incident if one is already open', function () {
    Http::fake([
        'https://example.com' => Http::response('Error', 500),
    ]);

    $monitor = makeMonitorForJob([
        'status'               => 'down',
        'threshold'            => 3,
        'consecutive_failures' => 3,
    ]);

    // Create an existing open incident
    \App\Models\Incident::create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(10),
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    $this->assertDatabaseCount('incidents', 1);
});

it('closes the incident when monitor recovers', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = makeMonitorForJob([
        'status'               => 'down',
        'consecutive_failures' => 3,
    ]);

    \App\Models\Incident::create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(10),
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    $incident = $monitor->incidents()->first();

    expect($incident->ended_at)->not->toBeNull();
    expect($incident->duration_seconds)->toBeGreaterThan(0);
});