<?php

use App\Models\DeviceBinding;
use App\Models\DeviceTransferRequest;
use App\Models\User;

function deviceFingerprint(): string
{
    return 'fp-' . str()->random(40);
}

function headerFor(string $fingerprint): array
{
    return ['X-Device-Fingerprint' => $fingerprint];
}

test('device status requires authentication', function () {
    $this->getJson('/api/device/status')->assertUnauthorized();
});

test('status returns null binding before any device binds', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/device/status', headerFor(deviceFingerprint()))
        ->assertOk()
        ->assertJsonPath('binding', null);
});

test('device can bind idempotently', function () {
    $user = User::factory()->create();
    $fingerprint = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => $fingerprint,
        'device_meta' => ['platform' => 'Android'],
    ])->assertStatus(201)->assertJsonPath('binding.device_fingerprint', $fingerprint);

    expect(DeviceBinding::where('user_id', $user->id)->exists())->toBeTrue();

    // Same device binding again is idempotent.
    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => $fingerprint,
    ])->assertStatus(201);

    expect(DeviceBinding::where('user_id', $user->id)->count())->toBe(1);

    // A second device colliding with an existing binding is rejected.
    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => deviceFingerprint(),
    ])->assertStatus(409);
});

test('binding is per user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)->postJson('/api/devices/bind', ['device_fingerprint' => deviceFingerprint()])->assertStatus(201);
    $this->actingAs($bob)->postJson('/api/devices/bind', ['device_fingerprint' => deviceFingerprint()])->assertStatus(201);

    expect(DeviceBinding::count())->toBe(2);
});

test('transfer request from a new device is created pending', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);

    $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
        'device_meta' => ['platform' => 'iOS'],
    ], headerFor($newDevice))
        ->assertStatus(201)
        ->assertJsonPath('request.status', DeviceTransferRequest::STATUS_PENDING)
        ->assertJsonPath('request.direction', 'outgoing');

    $request = DeviceTransferRequest::where('user_id', $user->id)->first();
    expect($request)->not->toBeNull();
    expect($request->requesting_fingerprint)->toBe($newDevice);
});

test('requesting transfer from the already bound device is rejected', function () {
    $user = User::factory()->create();
    $fingerprint = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $fingerprint])->assertStatus(201);

    $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $fingerprint,
    ])->assertStatus(409);
});

test('bound device can approve a transfer and binding moves to the new device', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);
    $transfer = $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice))->json('request');

    $this->actingAs($user)->postJson("/api/devices/transfer/requests/{$transfer['id']}/approve", [
        'device_fingerprint' => $oldDevice,
    ], headerFor($oldDevice))->assertOk();

    expect($user->fresh()->deviceBinding?->device_fingerprint)->toBe($newDevice);
    expect(DeviceTransferRequest::find($transfer['id'])->status)->toBe(DeviceTransferRequest::STATUS_APPROVED);
});

test('requester cannot approve its own transfer', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);
    $transfer = $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice))->json('request');

    $this->actingAs($user)->postJson("/api/devices/transfer/requests/{$transfer['id']}/approve", [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice))->assertStatus(403);

    expect(DeviceBinding::where('user_id', $user->id)->first()->device_fingerprint)->toBe($oldDevice);
});

test('bound device can reject a transfer', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);
    $transfer = $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice))->json('request');

    $this->actingAs($user)->postJson("/api/devices/transfer/requests/{$transfer['id']}/reject", [
        'device_fingerprint' => $oldDevice,
    ], headerFor($oldDevice))->assertOk();

    expect(DeviceTransferRequest::find($transfer['id'])->status)->toBe(DeviceTransferRequest::STATUS_REJECTED);
    expect(DeviceBinding::where('user_id', $user->id)->first()->device_fingerprint)->toBe($oldDevice);
});

test('transfer requests list exposes incoming and outgoing direction', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);
    $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice));

    // The bound (old) device sees the request as incoming.
    $this->actingAs($user)->getJson('/api/devices/transfer/requests', headerFor($oldDevice))
        ->assertOk()
        ->assertJsonCount(1, 'requests')
        ->assertJsonPath('requests.0.direction', 'incoming');

    // The requesting (new) device sees it as outgoing.
    $this->actingAs($user)->getJson('/api/devices/transfer/requests', headerFor($newDevice))
        ->assertOk()
        ->assertJsonPath('requests.0.direction', 'outgoing');
});

test('transfer request creates a notification for the account', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);
    $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice));

    expect($user->fresh()->notifications()->count())->toBe(1);
});

test('login tags the token with the device fingerprint and keeps one session per device', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $fingerprint = deviceFingerprint();

    $login = fn () => $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ], headerFor($fingerprint));

    $login()->assertOk();
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->device_fingerprint)->toBe($fingerprint);

    // Logging in again on the same device revokes the previous token.
    $login()->assertOk();
    expect($user->tokens()->count())->toBe(1);

    // A different device gets its own token (both remain until a transfer).
    $login()->assertOk();
    expect($user->tokens()->where('device_fingerprint', $fingerprint)->count())->toBe(1);
});

test('approving a transfer revokes the old device session', function () {
    $user = User::factory()->create();
    $oldDevice = deviceFingerprint();
    $newDevice = deviceFingerprint();

    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => $oldDevice])->assertStatus(201);

    // Old device + new device both hold active tokens.
    $oldToken = $user->createToken('old-device')->plainTextToken;
    $user->tokens()->latest()->first()->forceFill(['device_fingerprint' => $oldDevice])->save();

    $newToken = $user->createToken('new-device')->plainTextToken;
    $user->tokens()->latest()->first()->forceFill(['device_fingerprint' => $newDevice])->save();

    $transfer = $this->actingAs($user)->postJson('/api/devices/transfer/request', [
        'device_fingerprint' => $newDevice,
    ], headerFor($newDevice))->json('request');

    $this->actingAs($user)->postJson("/api/devices/transfer/requests/{$transfer['id']}/approve", [
        'device_fingerprint' => $oldDevice,
    ], headerFor($oldDevice))->assertOk();

    // The old device's token is gone; the new device's token survives.
    expect($user->tokens()->where('device_fingerprint', $oldDevice)->count())->toBe(0);
    expect($user->tokens()->where('device_fingerprint', $newDevice)->count())->toBe(1);
});