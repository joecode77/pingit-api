<?php

// tests/Feature/NotificationChannelTest.php

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function monitorWithUser(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->create(array_merge([
        'user_id' => $user->id,
    ], $overrides));
}

// ─────────────────────────────────────────────
// Create Notification Channel
// ─────────────────────────────────────────────

it('allows a user to add an email notification channel to a monitor', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'               => 'email',
        'value'              => 'alerts@example.com',
        'notify_on_down'     => true,
        'notify_on_recovery' => true,
        'notify_on_degraded' => false,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'value',
                'notify_on_down',
                'notify_on_recovery',
                'notify_on_degraded',
            ],
        ]);

    $this->assertDatabaseHas('notification_channels', [
        'monitor_id' => $monitor->id,
        'type'       => 'email',
        'value'      => 'alerts@example.com',
    ]);
});

it('allows a user to add a webhook notification channel to a monitor', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'               => 'webhook',
        'value'              => 'https://hooks.slack.com/services/abc123',
        'notify_on_down'     => true,
        'notify_on_recovery' => true,
        'notify_on_degraded' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'webhook');
});

it('fails to create a channel if type is invalid', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'  => 'sms',
        'value' => '+2348012345678',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('fails to create a channel if value is missing', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type' => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('fails to create an email channel if value is not a valid email', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'  => 'email',
        'value' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('fails to create a webhook channel if value is not a valid url', function () {
    $monitor = monitorWithUser();

    $response = $this->actingAs($monitor->user)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'  => 'webhook',
        'value' => 'not-a-url',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('returns 404 if monitor does not belong to the user', function () {
    $userOne = User::factory()->create();
    $monitor = monitorWithUser();

    $response = $this->actingAs($userOne)->postJson("/api/monitors/{$monitor->id}/channels", [
        'type'  => 'email',
        'value' => 'test@example.com',
    ]);

    $response->assertStatus(404);
});

// ─────────────────────────────────────────────
// List Notification Channels
// ─────────────────────────────────────────────

it('returns all notification channels for a monitor', function () {
    $monitor = monitorWithUser();

    NotificationChannel::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($monitor->user)->getJson("/api/monitors/{$monitor->id}/channels");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

// ─────────────────────────────────────────────
// Delete Notification Channel
// ─────────────────────────────────────────────

it('allows a user to delete a notification channel', function () {
    $monitor = monitorWithUser();

    $channel = NotificationChannel::factory()->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($monitor->user)->deleteJson("/api/monitors/{$monitor->id}/channels/{$channel->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Notification channel deleted successfully.']);

    $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
});

// ─────────────────────────────────────────────
// Webhook dispatch
// ─────────────────────────────────────────────

it('sends a webhook POST request when monitor goes down', function () {
    Http::fake([
        'https://hooks.example.com/*' => Http::response('OK', 200),
        '*'                           => Http::response('OK', 200),
    ]);

    $monitor = monitorWithUser([
        'status'               => 'pending',
        'threshold'            => 3,
        'consecutive_failures' => 2,
    ]);

    NotificationChannel::factory()->create([
        'monitor_id'     => $monitor->id,
        'type'           => 'webhook',
        'value'          => 'https://hooks.example.com/test',
        'notify_on_down' => true,
    ]);

    $checkService = new \App\Services\CheckService();
    $checkService->handleFailedCheck($monitor);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'hooks.example.com') &&
            $request->method() === 'POST';
    });
});