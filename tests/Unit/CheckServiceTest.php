<?php

// tests/Unit/CheckServiceTest.php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Services\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Illuminate\Foundation\Testing\TestCase::class, RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function makeMonitor(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->create(array_merge([
        'user_id'                    => $user->id,
        'status'                     => 'pending',
        'consecutive_failures'       => 0,
        'threshold'                  => 3,
        'response_time_threshold_ms' => null,
    ], $overrides));
}

// ─────────────────────────────────────────────
// is_up determination
// ─────────────────────────────────────────────

it('marks is_up as true for 200 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(200))->toBeTrue();
});

it('marks is_up as true for 201 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(201))->toBeTrue();
});

it('marks is_up as true for 301 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(301))->toBeTrue();
});

it('marks is_up as true for 302 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(302))->toBeTrue();
});

it('marks is_up as false for 400 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(400))->toBeFalse();
});

it('marks is_up as false for 404 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(404))->toBeFalse();
});

it('marks is_up as false for 500 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(500))->toBeFalse();
});

it('marks is_up as false for 503 status code', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(503))->toBeFalse();
});

it('marks is_up as false for status code 0 (timeout)', function () {
    $checkService = new CheckService();

    expect($checkService->isUp(0))->toBeFalse();
});

// ─────────────────────────────────────────────
// Status transitions — successful checks
// ─────────────────────────────────────────────

it('sets status to up after a successful check within response time threshold', function () {
    $monitor      = makeMonitor(['response_time_threshold_ms' => 1000]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 500);

    expect($monitor->fresh()->status)->toBe('up');
});

it('sets status to degraded when response time exceeds threshold', function () {
    $monitor      = makeMonitor(['response_time_threshold_ms' => 1000]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    expect($monitor->fresh()->status)->toBe('degraded');
});

it('sets status to up when no response time threshold is configured', function () {
    $monitor      = makeMonitor(['response_time_threshold_ms' => null]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 5000);

    expect($monitor->fresh()->status)->toBe('up');
});

it('resets consecutive_failures to zero after a successful check', function () {
    $monitor      = makeMonitor(['consecutive_failures' => 2]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    expect($monitor->fresh()->consecutive_failures)->toBe(0);
});

// ─────────────────────────────────────────────
// Status transitions — failed checks
// ─────────────────────────────────────────────

it('increments consecutive_failures after a failed check', function () {
    $monitor      = makeMonitor(['consecutive_failures' => 0]);
    $checkService = new CheckService();

    $checkService->handleFailedCheck($monitor);

    expect($monitor->fresh()->consecutive_failures)->toBe(1);
});

it('does not mark monitor as down before threshold is reached', function () {
    $monitor      = makeMonitor(['threshold' => 3, 'consecutive_failures' => 1]);
    $checkService = new CheckService();

    $checkService->handleFailedCheck($monitor);

    expect($monitor->fresh()->status)->not->toBe('down');
});

it('marks monitor as down when consecutive failures reach threshold', function () {
    $monitor      = makeMonitor(['threshold' => 3, 'consecutive_failures' => 2]);
    $checkService = new CheckService();

    $checkService->handleFailedCheck($monitor);

    expect($monitor->fresh()->status)->toBe('down');
});

it('does not change status to down again if already down', function () {
    $monitor      = makeMonitor(['threshold' => 3, 'consecutive_failures' => 5, 'status' => 'down']);
    $checkService = new CheckService();

    $checkService->handleFailedCheck($monitor);

    expect($monitor->fresh()->status)->toBe('down');
    expect($monitor->fresh()->consecutive_failures)->toBe(6);
});

// ─────────────────────────────────────────────
// Recovery
// ─────────────────────────────────────────────

it('transitions status from down to up on recovery', function () {
    $monitor      = makeMonitor(['status' => 'down', 'consecutive_failures' => 3]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    expect($monitor->fresh()->status)->toBe('up');
});

it('transitions status from degraded to up on fast recovery', function () {
    $monitor      = makeMonitor([
        'status'                     => 'degraded',
        'response_time_threshold_ms' => 1000,
    ]);
    $checkService = new CheckService();

    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    expect($monitor->fresh()->status)->toBe('up');
});

// ─────────────────────────────────────────────
// Uptime percentage
// ─────────────────────────────────────────────

it('returns null uptime percentage when no checks exist', function () {
    $monitor      = makeMonitor();
    $checkService = new CheckService();

    expect($checkService->uptimePercentage($monitor))->toBeNull();
});

it('returns 100 percent when all checks are successful', function () {
    $monitor = makeMonitor();

    MonitorCheck::factory()->count(10)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => true,
    ]);

    $checkService = new CheckService();

    expect($checkService->uptimePercentage($monitor))->toBe(100.0);
});

it('returns 0 percent when all checks have failed', function () {
    $monitor = makeMonitor();

    MonitorCheck::factory()->count(10)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => false,
    ]);

    $checkService = new CheckService();

    expect($checkService->uptimePercentage($monitor))->toBe(0.0);
});

it('calculates uptime percentage correctly with mixed results', function () {
    $monitor = makeMonitor();

    MonitorCheck::factory()->count(90)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => true,
    ]);

    MonitorCheck::factory()->count(10)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => false,
    ]);

    $checkService = new CheckService();

    expect($checkService->uptimePercentage($monitor))->toBe(90.0);
});