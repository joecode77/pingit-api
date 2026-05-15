<?php

// tests/Feature/NotificationTest.php

use App\Mail\MonitorDegradedMail;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Monitor;
use App\Models\User;
use App\Services\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function makeMonitorWithUser(array $overrides = []): Monitor
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
// Down notifications
// ─────────────────────────────────────────────

it('sends a down notification when monitor reaches failure threshold', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'status'               => 'pending',
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class, function ($mail) use ($monitor) {
        return $mail->hasTo($monitor->user->email);
    });
});

it('does not send a down notification before threshold is reached', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 1,
        'status'               => 'pending',
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertNotSent(MonitorDownMail::class);
});

it('does not send a down notification if monitor is already down', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 5,
        'status'               => 'down',
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertNotSent(MonitorDownMail::class);
});

// ─────────────────────────────────────────────
// Recovery notifications
// ─────────────────────────────────────────────

it('sends a recovery notification when monitor recovers from down', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status'               => 'down',
        'consecutive_failures' => 3,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertSent(MonitorRecoveredMail::class, function ($mail) use ($monitor) {
        return $mail->hasTo($monitor->user->email);
    });
});

it('does not send a recovery notification if monitor was not down', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status' => 'pending',
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertNotSent(MonitorRecoveredMail::class);
});

it('does not send a recovery notification if monitor was degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status'                     => 'degraded',
        'response_time_threshold_ms' => 1000,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertNotSent(MonitorRecoveredMail::class);
});

// ─────────────────────────────────────────────
// Degraded notifications
// ─────────────────────────────────────────────

it('sends a degraded notification when monitor becomes degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status'                     => 'up',
        'response_time_threshold_ms' => 1000,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    Mail::assertSent(MonitorDegradedMail::class, function ($mail) use ($monitor) {
        return $mail->hasTo($monitor->user->email);
    });
});

it('does not send a degraded notification if monitor was already degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status'                     => 'degraded',
        'response_time_threshold_ms' => 1000,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    Mail::assertNotSent(MonitorDegradedMail::class);
});

it('does not send a degraded notification if no response time threshold is set', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'status'                     => 'up',
        'response_time_threshold_ms' => null,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 5000);

    Mail::assertNotSent(MonitorDegradedMail::class);
});

// ─────────────────────────────────────────────
// Notification cooldowns
// ─────────────────────────────────────────────

it('does not send a down notification if cooldown period has not passed', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'check_interval'       => 5,
        'status'               => 'pending',
        // Last notified just now — cooldown not expired
        'last_notified_at'     => now()->subMinutes(1),
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertNotSent(MonitorDownMail::class);
});

it('sends a down notification if cooldown period has passed', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'check_interval'       => 5,
        'status'               => 'pending',
        // Last notified long ago — cooldown expired
        'last_notified_at'     => now()->subHours(2),
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class);
});

it('sends a down notification if never notified before', function () {
    Mail::fake();

    $monitor = makeMonitorWithUser([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'status'               => 'pending',
        'last_notified_at'     => null,
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class);
});