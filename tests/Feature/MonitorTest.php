<?php

// tests/Feature/MonitorTest.php

use App\Models\Monitor;
use App\Models\User;
use Carbon\Carbon;
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

// ─────────────────────────────────────────────
// Pause & Resume
// ─────────────────────────────────────────────

it('allows a user to pause a monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'up']);

    $response = $this->actingAs($user)->postJson("/api/monitors/{$monitor->id}/pause");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'paused');

    $this->assertDatabaseHas('monitors', [
        'id'     => $monitor->id,
        'status' => 'paused',
    ]);
});

it('allows a user to resume a paused monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'paused']);

    $response = $this->actingAs($user)->postJson("/api/monitors/{$monitor->id}/resume");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'pending');
});

it('returns 404 if user tries to pause another users monitor', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->postJson("/api/monitors/{$monitor->id}/pause");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 if user tries to resume another users monitor', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id, 'status' => 'paused']);

    $response = $this->actingAs($userOne)->postJson("/api/monitors/{$monitor->id}/resume");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Filter & Sort
// ─────────────────────────────────────────────

it('filters monitors by status', function () {
    $user = authUser();

    Monitor::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'up']);
    Monitor::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'down']);

    $response = $this->actingAs($user)->getJson('/api/monitors?status=up');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('sorts monitors by name ascending', function () {
    $user = authUser();

    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Zebra Site']);
    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Apple Site']);
    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Mango Site']);

    $response = $this->actingAs($user)->getJson('/api/monitors?sort=name&direction=asc');

    $data = $response->json('data');

    expect($data[0]['name'])->toBe('Apple Site');
    expect($data[1]['name'])->toBe('Mango Site');
    expect($data[2]['name'])->toBe('Zebra Site');
});

it('searches monitors by name', function () {
    $user = authUser();

    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'My Blog']);
    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'My Store']);
    Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Company Site']);

    $response = $this->actingAs($user)->getJson('/api/monitors?search=My');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

// ─────────────────────────────────────────────
// Dashboard
// ─────────────────────────────────────────────

it('returns dashboard summary for the authenticated user', function () {
    $user = authUser();

    Monitor::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'up']);
    Monitor::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'down']);
    Monitor::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'degraded']);
    Monitor::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'paused']);

    $response = $this->actingAs($user)->getJson('/api/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total',
                'up',
                'down',
                'degraded',
                'paused',
                'overall_uptime_percentage',
            ],
        ])
        ->assertJsonPath('data.total', 5)
        ->assertJsonPath('data.up', 2)
        ->assertJsonPath('data.down', 1)
        ->assertJsonPath('data.degraded', 1)
        ->assertJsonPath('data.paused', 1);
});

it('returns 401 for dashboard if unauthenticated', function () {
    $response = $this->getJson('/api/dashboard');

    $response->assertStatus(401);
});

// ─────────────────────────────────────────────
// Incidents
// ─────────────────────────────────────────────

it('returns incident history for a monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\Incident::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/incidents");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'monitor_id',
                    'started_at',
                    'ended_at',
                    'duration_seconds',
                    'is_ongoing',
                ],
            ],
        ]);
});

it('returns 404 for incidents if monitor does not exist', function () {
    $user = authUser();

    $response = $this->actingAs($user)->getJson('/api/monitors/999/incidents');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 for incidents if monitor belongs to another user', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}/incidents");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Response Time Trends
// ─────────────────────────────────────────────

it('returns response time trends for a monitor', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->count(10)->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => true,
        'response_time_ms' => 500,
        'checked_at'       => now()->subDays(3),
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/response-times?period=7d");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'average_ms',
                'min_ms',
                'max_ms',
                'period',
            ],
        ]);

    $data = $response->json('data');

    expect($data['average_ms'])->toBe(500)
        ->and($data['min_ms'])->toBe(500)
        ->and($data['max_ms'])->toBe(500)
        ->and($data['period'])->toBe('7d');
});

it('returns null trends when no successful checks exist', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/response-times?period=7d");

    $response->assertStatus(200)
        ->assertJsonPath('data.average_ms', null)
        ->assertJsonPath('data.min_ms', null)
        ->assertJsonPath('data.max_ms', null);
});

it('defaults to 7d period if none specified', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/response-times");

    $response->assertStatus(200)
        ->assertJsonPath('data.period', '7d');
});

it('returns 404 for response times if monitor belongs to another user', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}/response-times");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// CSV Export
// ─────────────────────────────────────────────

it('exports check history as a csv file', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->count(5)->create([
        'monitor_id' => $monitor->id,
    ]);

    $response = $this->actingAs($user)->get("/api/monitors/{$monitor->id}/history/export");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', "attachment; filename=monitor-{$monitor->id}-history.csv");
});

it('returns 404 for csv export if monitor does not exist', function () {
    $user = authUser();

    $response = $this->actingAs($user)->getJson('/api/monitors/999/history/export');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 for csv export if monitor belongs to another user', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}/history/export");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

// ─────────────────────────────────────────────
// Daily Stats
// ─────────────────────────────────────────────

it('returns daily stats with correct structure', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->count(5)->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => true,
        'response_time_ms' => 200,
        'checked_at'       => now(),
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'date',
                    'total_checks',
                    'successful_checks',
                    'failed_checks',
                    'uptime_percentage',
                    'avg_response_ms',
                    'min_response_ms',
                    'max_response_ms',
                ],
            ],
        ]);
});

it('returns 30 days by default', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats");

    $response->assertStatus(200)
        ->assertJsonCount(30, 'data');
});

it('respects the days query parameter', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=7");

    $response->assertStatus(200)
        ->assertJsonCount(7, 'data');
});

it('caps days at 90', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=200");

    $response->assertStatus(200)
        ->assertJsonCount(90, 'data');
});

it('correctly counts successful and failed checks per day', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $today = Carbon::today();

    \App\Models\MonitorCheck::factory()->count(8)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => true,
        'checked_at' => $today,
    ]);

    \App\Models\MonitorCheck::factory()->count(2)->create([
        'monitor_id' => $monitor->id,
        'is_up'      => false,
        'checked_at' => $today,
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=1");

    $data = $response->json('data.0');

    expect($data['total_checks'])->toBe(10)
        ->and($data['successful_checks'])->toBe(8)
        ->and($data['failed_checks'])->toBe(2)
        ->and((float) $data['uptime_percentage'])->toBe(80.0);
});

it('correctly calculates avg min and max response times', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $today = Carbon::today();

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => true,
        'response_time_ms' => 100,
        'checked_at'       => $today,
    ]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => true,
        'response_time_ms' => 200,
        'checked_at'       => $today,
    ]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => true,
        'response_time_ms' => 300,
        'checked_at'       => $today,
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=1");

    $data = $response->json('data.0');

    expect((float) $data['avg_response_ms'])->toBe(200.0)
        ->and($data['min_response_ms'])->toBe(100)
        ->and($data['max_response_ms'])->toBe(300);
});

it('returns null response time metrics when no successful checks exist', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id'       => $monitor->id,
        'is_up'            => false,
        'response_time_ms' => null,
        'checked_at'       => Carbon::today(),
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=1");

    $data = $response->json('data.0');

    expect($data['avg_response_ms'])->toBeNull()
        ->and($data['min_response_ms'])->toBeNull()
        ->and($data['max_response_ms'])->toBeNull();
});

it('returns zero counts and null metrics for days with no checks', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=7");

    $response->assertStatus(200);

    foreach ($response->json('data') as $day) {
        expect($day['total_checks'])->toBe(0)
            ->and($day['successful_checks'])->toBe(0)
            ->and($day['failed_checks'])->toBe(0)
            ->and($day['uptime_percentage'])->toBeNull()
            ->and($day['avg_response_ms'])->toBeNull();
    }
});

it('returns dates in ascending order', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=7");

    $dates  = collect($response->json('data'))->pluck('date')->toArray();
    $sorted = $dates;
    sort($sorted);

    expect($dates)->toBe($sorted);
});

it('only counts checks within the requested date range', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'is_up'      => true,
        'checked_at' => now()->subDays(3),
    ]);

    \App\Models\MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'is_up'      => true,
        'checked_at' => now()->subDays(40),
    ]);

    $response = $this->actingAs($user)->getJson("/api/monitors/{$monitor->id}/daily-stats?days=7");

    $totalChecks = collect($response->json('data'))->sum('total_checks');

    expect($totalChecks)->toBe(1);
});

it('returns 401 for daily stats if unauthenticated', function () {
    $user    = authUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson("/api/monitors/{$monitor->id}/daily-stats");

    $response->assertStatus(401);
});

it('returns 404 for daily stats if monitor does not exist', function () {
    $user = authUser();

    $response = $this->actingAs($user)->getJson('/api/monitors/999/daily-stats');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});

it('returns 404 for daily stats if monitor belongs to another user', function () {
    $userOne = authUser();
    $userTwo = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->getJson("/api/monitors/{$monitor->id}/daily-stats");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Monitor not found.']);
});
