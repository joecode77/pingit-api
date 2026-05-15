<?php

// tests/Feature/MonitorTest.php

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function authUser(): User
{
    $user = User::factory()->create();
    return $user;
}

function validMonitorData(array $overrides = []): array
{
    return array_merge([
        'url'            => 'https://example.com',
        'name'           => 'Example Site',
        'check_interval' => 5,
        'threshold'      => 3,
    ], $overrides);
}

// ─────────────────────────────────────────────
// Create Monitor
// ─────────────────────────────────────────────

it('allows an authenticated user to create a monitor', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData());

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'url',
                'name',
                'check_interval',
                'threshold',
                'status',
                'last_checked_at',
                'uptime_percentage',
                'created_at',
            ],
        ])
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.url', 'https://example.com');

    $this->assertDatabaseHas('monitors', [
        'url'     => 'https://example.com',
        'user_id' => $user->id,
    ]);
});

it('returns 401 if unauthenticated user tries to create a monitor', function () {
    $response = $this->postJson('/api/monitors', validMonitorData());

    $response->assertStatus(401);
});

it('fails to create a monitor if url is missing', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData(['url' => '']));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('fails to create a monitor if url is invalid', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData(['url' => 'not-a-url']));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('fails to create a monitor if url is a duplicate for the same user', function () {
    $user = authUser();

    Monitor::factory()->create(['user_id' => $user->id, 'url' => 'https://example.com']);

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData());

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('allows two different users to monitor the same url', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();

    Monitor::factory()->create(['user_id' => $userOne->id, 'url' => 'https://example.com']);

    $response = $this->actingAs($userTwo)->postJson('/api/monitors', validMonitorData());

    $response->assertStatus(201);
});

it('fails to create a monitor if check_interval is less than 1', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData(['check_interval' => 0]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['check_interval']);
});

it('fails to create a monitor if check_interval is greater than 60', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData(['check_interval' => 61]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['check_interval']);
});

it('fails to create a monitor if threshold is less than 1', function () {
    $user = authUser();

    $response = $this->actingAs($user)->postJson('/api/monitors', validMonitorData(['threshold' => 0]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['threshold']);
});

// ─────────────────────────────────────────────
// List Monitors
// ─────────────────────────────────────────────

it('returns a list of monitors for the authenticated user', function () {
    $user = authUser();

    Monitor::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/monitors');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'url',
                    'name',
                    'check_interval',
                    'threshold',
                    'status',
                    'last_checked_at',
                    'uptime_percentage',
                    'created_at',
                ],
            ],
        ]);
});

it('does not return monitors belonging to other users', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();

    Monitor::factory()->count(3)->create(['user_id' => $userOne->id]);
    Monitor::factory()->count(2)->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson('/api/monitors');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('returns 401 if unauthenticated user tries to list monitors', function () {
    $response = $this->getJson('/api/monitors');

    $response->assertStatus(401);
});

// ─────────────────────────────────────────────
// Get Single Monitor
// ─────────────────────────────────────────────

it('returns a single monitor for the authenticated user', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $monitor->id);
});

it('returns 404 if monitor does not exist', function () {
    $user = authUser();

    $response = $this->actingAs($user)->getJson('/api/monitors/999');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 if user tries to view another users monitor', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Update Monitor
// ─────────────────────────────────────────────

it('allows an authenticated user to update their monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/monitors/{$monitor->id}", [
        'name'           => 'Updated Name',
        'check_interval' => 10,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.check_interval', 10);
});

it('returns 404 if user tries to update another users monitor', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->putJson("/api/monitors/{$monitor->id}", [
        'name' => 'Hacked',
    ]);

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Delete Monitor
// ─────────────────────────────────────────────

it('allows an authenticated user to delete their monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/monitors/{$monitor->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Monitor deleted successfully.']);

    $this->assertSoftDeleted('monitors', ['id' => $monitor->id]);
});

it('returns 404 if user tries to delete another users monitor', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->deleteJson("/api/monitors/{$monitor->id}");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Check History
// ─────────────────────────────────────────────

it('returns paginated check history for a monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->count(20)->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/history");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'monitor_id',
                    'status_code',
                    'response_time_ms',
                    'is_up',
                    'checked_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.current_page', 1);
});

it('returns check history ordered by checked_at descending', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(10),
    ]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/history");

    $data = $response->json('data');

    expect($data[0]['checked_at'])->toBeGreaterThan($data[1]['checked_at']);
    expect($data[1]['checked_at'])->toBeGreaterThan($data[2]['checked_at']);
});

it('respects per_page query parameter', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->count(20)->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/history?per_page=5");

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonCount(5, 'data');
});

it('returns 404 for check history if monitor does not exist', function () {
    $user = authUser();

    $response = $this->actingAs($user)->getJson('/api/monitors/999/history');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 for check history if monitor belongs to another user', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}/history");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});