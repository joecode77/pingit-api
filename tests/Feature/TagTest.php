<?php

// tests/Feature/TagTest.php

use App\Models\Monitor;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function tagUser(): User
{
    return User::factory()->create();
}

// ─────────────────────────────────────────────
// Create Tag
// ─────────────────────────────────────────────

it('allows a user to create a tag', function () {
    $user = tagUser();

    $response = $this->actingAs($user)->postJson('/api/tags', [
        'name' => 'production',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'created_at'],
        ])
        ->assertJsonPath('data.name', 'production');

    $this->assertDatabaseHas('tags', [
        'user_id' => $user->id,
        'name'    => 'production',
    ]);
});

it('fails to create a tag if name is missing', function () {
    $user = tagUser();

    $response = $this->actingAs($user)->postJson('/api/tags', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('fails to create a duplicate tag for the same user', function () {
    $user = tagUser();

    Tag::factory()->create(['user_id' => $user->id, 'name' => 'production']);

    $response = $this->actingAs($user)->postJson('/api/tags', [
        'name' => 'production',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('allows two users to have the same tag name', function () {
    $userOne = tagUser();
    $userTwo = User::factory()->create();

    Tag::factory()->create(['user_id' => $userOne->id, 'name' => 'production']);

    $response = $this->actingAs($userTwo)->postJson('/api/tags', [
        'name' => 'production',
    ]);

    $response->assertStatus(201);
});

// ─────────────────────────────────────────────
// List Tags
// ─────────────────────────────────────────────

it('returns all tags for the authenticated user', function () {
    $user = tagUser();

    Tag::factory()->count(3)->create(['user_id' => $user->id]);
    Tag::factory()->count(2)->create(); // other user's tags

    $response = $this->actingAs($user)->getJson('/api/tags');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('returns 401 if unauthenticated user tries to list tags', function () {
    $response = $this->getJson('/api/tags');

    $response->assertStatus(401);
});

// ─────────────────────────────────────────────
// Delete Tag
// ─────────────────────────────────────────────

it('allows a user to delete their tag', function () {
    $user = tagUser();
    $tag  = Tag::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/tags/{$tag->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tag deleted successfully.']);

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

it('returns 404 if user tries to delete another users tag', function () {
    $userOne = tagUser();
    $userTwo = User::factory()->create();
    $tag     = Tag::factory()->create(['user_id' => $userTwo->id]);

    $response = $this->actingAs($userOne)->deleteJson("/api/tags/{$tag->id}");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Tag not found.']);
});

// ─────────────────────────────────────────────
// Attach & Detach Tags from Monitors
// ─────────────────────────────────────────────

it('allows a user to attach a tag to a monitor', function () {
    $user    = tagUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    $tag     = Tag::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/api/monitors/{$monitor->id}/tags", [
        'tag_id' => $tag->id,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tag attached successfully.']);

    $this->assertDatabaseHas('monitor_tag', [
        'monitor_id' => $monitor->id,
        'tag_id'     => $tag->id,
    ]);
});

it('allows a user to detach a tag from a monitor', function () {
    $user    = tagUser();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    $tag     = Tag::factory()->create(['user_id' => $user->id]);

    $monitor->tags()->attach($tag->id);

    $response = $this->actingAs($user)->deleteJson("/api/monitors/{$monitor->id}/tags/{$tag->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tag detached successfully.']);

    $this->assertDatabaseMissing('monitor_tag', [
        'monitor_id' => $monitor->id,
        'tag_id'     => $tag->id,
    ]);
});

it('filters monitors by tag', function () {
    $user    = tagUser();
    $tag     = Tag::factory()->create(['user_id' => $user->id, 'name' => 'production']);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    Monitor::factory()->count(2)->create(['user_id' => $user->id]);

    $monitor->tags()->attach($tag->id);

    $response = $this->actingAs($user)->getJson("/api/monitors?tag=production");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});