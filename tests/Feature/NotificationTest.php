<?php

// tests/Feature/NotificationTest.php

use App\Mail\MonitorDegradedMail;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function makeMonitorWithChannel(array $monitorOverrides = [], array $channelOverrides = []): Monitor
{
    $user = User::factory()->create();

    $monitor = Monitor::factory()->create(array_merge([
        'user_id'                    => $user->id,
        'status'                     => 'pending',
        'consecutive_failures'       => 0,
        'threshold'                  => 3,
        'response_time_threshold_ms' => null,
    ], $monitorOverrides));

    NotificationChannel::factory()->create(array_merge([
        'monitor_id'         => $monitor->id,
        'type'               => 'email',
        'value'              => 'alerts@example.com',
        'notify_on_down'     => true,
        'notify_on_recovery' => true,
        'notify_on_degraded' => true,
    ], $channelOverrides));

    return $monitor;
}

// ─────────────────────────────────────────────
// Down notifications
// ─────────────────────────────────────────────

it('sends a down notification to configured channels when monitor reaches failure threshold', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'status'               => 'pending',
    ], [
        'notify_on_down' => true,
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class, function ($mail) {
        return $mail->hasTo('alerts@example.com');
    });
});

it('does not send a down notification before threshold is reached', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
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

    $monitor = makeMonitorWithChannel([
        'threshold'            => 3,
        'consecutive_failures' => 5,
        'status'               => 'down',
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertNotSent(MonitorDownMail::class);
});

it('does not send a down notification if channel has notify_on_down disabled', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'status'               => 'pending',
    ], [
        'notify_on_down' => false,
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertNotSent(MonitorDownMail::class);
});

it('sends down notifications to multiple configured channels', function () {
    Mail::fake();

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id'              => $user->id,
        'status'               => 'pending',
        'threshold'            => 3,
        'consecutive_failures' => 2,
    ]);

    NotificationChannel::factory()->create([
        'monitor_id'     => $monitor->id,
        'type'           => 'email',
        'value'          => 'alerts@example.com',
        'notify_on_down' => true,
    ]);

    NotificationChannel::factory()->create([
        'monitor_id'     => $monitor->id,
        'type'           => 'email',
        'value'          => 'oncall@example.com',
        'notify_on_down' => true,
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class, fn ($mail) => $mail->hasTo('alerts@example.com'));
    Mail::assertSent(MonitorDownMail::class, fn ($mail) => $mail->hasTo('oncall@example.com'));
});

// ─────────────────────────────────────────────
// Recovery notifications
// ─────────────────────────────────────────────

it('sends a recovery notification to configured channels when monitor recovers from down', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'               => 'down',
        'consecutive_failures' => 3,
    ], [
        'notify_on_recovery' => true,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertSent(MonitorRecoveredMail::class, function ($mail) {
        return $mail->hasTo('alerts@example.com');
    });
});

it('does not send a recovery notification if monitor was not down', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status' => 'pending',
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertNotSent(MonitorRecoveredMail::class);
});

it('does not send a recovery notification if monitor was degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'                     => 'degraded',
        'response_time_threshold_ms' => 1000,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertNotSent(MonitorRecoveredMail::class);
});

it('does not send a recovery notification if channel has notify_on_recovery disabled', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'               => 'down',
        'consecutive_failures' => 3,
    ], [
        'notify_on_recovery' => false,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 200);

    Mail::assertNotSent(MonitorRecoveredMail::class);
});

// ─────────────────────────────────────────────
// Degraded notifications
// ─────────────────────────────────────────────

it('sends a degraded notification to configured channels when monitor becomes degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'                     => 'up',
        'response_time_threshold_ms' => 1000,
    ], [
        'notify_on_degraded' => true,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    Mail::assertSent(MonitorDegradedMail::class, function ($mail) {
        return $mail->hasTo('alerts@example.com');
    });
});

it('does not send a degraded notification if monitor was already degraded', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'                     => 'degraded',
        'response_time_threshold_ms' => 1000,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    Mail::assertNotSent(MonitorDegradedMail::class);
});

it('does not send a degraded notification if no response time threshold is set', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'                     => 'up',
        'response_time_threshold_ms' => null,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 5000);

    Mail::assertNotSent(MonitorDegradedMail::class);
});

it('does not send a degraded notification if channel has notify_on_degraded disabled', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
        'status'                     => 'up',
        'response_time_threshold_ms' => 1000,
    ], [
        'notify_on_degraded' => false,
    ]);

    $checkService = new CheckService();
    $checkService->handleSuccessfulCheck($monitor, responseTimeMs: 1500);

    Mail::assertNotSent(MonitorDegradedMail::class);
});

// ─────────────────────────────────────────────
// Notification cooldowns
// ─────────────────────────────────────────────

it('does not send a down notification if cooldown period has not passed', function () {
    Mail::fake();

    $monitor = makeMonitorWithChannel([
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

    $monitor = makeMonitorWithChannel([
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

    $monitor = makeMonitorWithChannel([
        'threshold'            => 3,
        'consecutive_failures' => 2,
        'status'               => 'pending',
        'last_notified_at'     => null,
    ]);

    $checkService = new CheckService();
    $checkService->handleFailedCheck($monitor);

    Mail::assertSent(MonitorDownMail::class);
});
