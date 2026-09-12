<?php

use App\Enums\UserRole;
use App\Models\FaceEnrollment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function hybridGrant(User $user): User
{
    $user->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'position' => UserRole::SUPER_ADMIN->value,
        'assigned_at' => now(),
    ]);
    return $user;
}

beforeEach(function () {
    $this->seed([\Database\Seeders\OrganizationSeeder::class]);
});

test('same physical device different browser can bind via face-verified without old device approval', function () {
    $user = hybridGrant(User::factory()->create());
    // Enroll face - required for instant path
    FaceEnrollment::create([
        'user_id' => $user->id,
        'descriptors' => [[0.1, 0.2, 0.3]],
        'enrolled_at' => now(),
    ]);

    $metaA = ['platform' => 'Win32', 'ua' => 'Mozilla Chrome', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $metaB = ['platform' => 'Win32', 'ua' => 'Mozilla Firefox', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];

    // Bind first browser
    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => 'fp-chrome-'.str_repeat('a', 32),
        'device_meta' => $metaA,
    ])->assertCreated();

    // Second browser same hardware but different UA - via face-verified should succeed
    $this->actingAs($user)->postJson('/api/devices/bind/face-verified', [
        'device_fingerprint' => 'fp-firefox-'.str_repeat('b', 32),
        'device_meta' => $metaB,
    ])->assertOk()
      ->assertJsonPath('binding.trusted_fingerprints.0', 'fp-firefox-'.str_repeat('b', 32));

    // Verify status says is_trusted for second fingerprint (similar => trusted)
    $this->actingAs($user)->withHeader('X-Device-Fingerprint', 'fp-firefox-'.str_repeat('b', 32))
        ->withHeader('X-Device-Meta', json_encode($metaB))
        ->getJson('/api/device/status')
        ->assertOk()
        ->assertJsonPath('is_trusted', true);
        // is_similar is false when already trusted (we skip similar check if trusted), so don't assert it

    // Original Chrome should still be valid via trusted? Check is_similar path
    $this->actingAs($user)->withHeader('X-Device-Fingerprint', 'fp-chrome-'.str_repeat('a', 32))
        ->withHeader('X-Device-Meta', json_encode($metaA))
        ->getJson('/api/device/status')
        ->assertOk()
        ->assertJsonPath('is_trusted', true);
});

test('different hardware cannot use face-verified instant bind', function () {
    $user = hybridGrant(User::factory()->create());
    FaceEnrollment::create([
        'user_id' => $user->id,
        'descriptors' => [[0.1, 0.2]],
        'enrolled_at' => now(),
    ]);
    $metaA = ['platform' => 'Win32', 'ua' => 'Chrome', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $metaPhone = ['platform' => 'iPhone', 'ua' => 'Safari', 'cores' => 4, 'screen' => '390x844', 'language' => 'en-US'];

    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => 'fp-desktop-'.str_repeat('a', 32),
        'device_meta' => $metaA,
    ])->assertCreated();

    $this->actingAs($user)->postJson('/api/devices/bind/face-verified', [
        'device_fingerprint' => 'fp-phone-'.str_repeat('b', 32),
        'device_meta' => $metaPhone,
    ])->assertStatus(403)
      ->assertJsonPath('message', 'This device does not appear to be the same hardware as your bound device. Please request transfer from your old device.');
});

test('face enrollment required for instant bind', function () {
    $user = hybridGrant(User::factory()->create());
    // No face enrollment
    $metaA = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $metaB = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];

    $this->actingAs($user)->postJson('/api/devices/bind', [
        'device_fingerprint' => 'fp-a-'.str_repeat('a', 32),
        'device_meta' => $metaA,
    ])->assertCreated();

    $this->actingAs($user)->postJson('/api/devices/bind/face-verified', [
        'device_fingerprint' => 'fp-b-'.str_repeat('b', 32),
        'device_meta' => $metaB,
    ])->assertStatus(403)
      ->assertJsonPath('message', 'Face enrollment required for instant bind. Please enroll face first or request transfer from old device.');
});

test('status returns is_similar for same hardware different browser', function () {
    $user = hybridGrant(User::factory()->create());
    FaceEnrollment::create(['user_id' => $user->id, 'descriptors' => [[0.1, 0.2]], 'enrolled_at' => now()]);
    $metaA = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $metaB = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => 'fp-a-'.str_repeat('a', 32), 'device_meta' => $metaA])->assertCreated();

    $this->actingAs($user)->withHeader('X-Device-Fingerprint', 'fp-b-'.str_repeat('b', 32))
        ->withHeader('X-Device-Meta', json_encode($metaB))
        ->getJson('/api/device/status')
        ->assertOk()
        ->assertJsonPath('is_similar', true)
        ->assertJsonPath('is_trusted', false);
});

test('regular bind with similar hardware now adds to trusted instead of 409', function () {
    $user = hybridGrant(User::factory()->create());
    $metaA = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $metaB = ['platform' => 'Win32', 'cores' => 8, 'screen' => '1920x1080', 'language' => 'en-US'];
    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => 'fp-a-'.str_repeat('a', 32), 'device_meta' => $metaA])->assertCreated();
    // Second bind with similar hardware should now succeed via trusted path (201, not 409) even without face-verified endpoint
    $this->actingAs($user)->postJson('/api/devices/bind', ['device_fingerprint' => 'fp-b-'.str_repeat('b', 32), 'device_meta' => $metaB])->assertCreated();
    expect(\App\Models\DeviceBinding::where('user_id', $user->id)->first()->trusted_fingerprints)->toContain('fp-b-'.str_repeat('b', 32));
});
