<?php

use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('can list notifications', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/notifications');

    $response->assertStatus(200)
        ->assertJsonStructure(['notifications', 'unread_count']);
});

test('can mark notification as read', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\GeneralNotification',
        'data' => ['title' => 'Test', 'message' => 'Test message'],
    ]);

    $response = $this->actingAs($user)->postJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('can mark all notifications as read', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\GeneralNotification',
        'data' => ['title' => 'Test', 'message' => 'Test'],
    ]);

    $response = $this->actingAs($user)->postJson('/api/notifications/read-all');

    $response->assertStatus(200);
    expect($user->fresh()->unreadNotifications->count())->toBe(0);
});

test('can update push subscription', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/notifications/push-token', [
        'endpoint' => 'https://push.example.com/sub/abc-123',
        'keys' => [
            'p256dh' => str_repeat('A', 43),
            'auth' => str_repeat('B', 43),
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.push_subscriptions_count', 1);

    expect($user->fresh()->pushSubscriptions()->count())->toBe(1);
});

test('upserts push subscription on duplicate endpoint', function () {
    $user = User::factory()->create();

    $payload = [
        'endpoint' => 'https://push.example.com/sub/abc-123',
        'keys' => ['p256dh' => str_repeat('A', 43), 'auth' => str_repeat('B', 43)],
    ];

    $this->actingAs($user)->putJson('/api/notifications/push-token', $payload);
    $this->actingAs($user)->putJson('/api/notifications/push-token', $payload);

    expect($user->fresh()->pushSubscriptions()->count())->toBe(1);
});

test('can remove push subscription', function () {
    $user = User::factory()->create();
    $user->pushSubscriptions()->create([
        'endpoint' => 'https://push.example.com/sub/abc-123',
        'p256dh' => str_repeat('A', 43),
        'auth' => str_repeat('B', 43),
    ]);

    $response = $this->actingAs($user)->deleteJson('/api/notifications/push-subscription', [
        'endpoint' => 'https://push.example.com/sub/abc-123',
    ]);

    $response->assertStatus(200);
    expect($user->fresh()->pushSubscriptions()->count())->toBe(0);
});
