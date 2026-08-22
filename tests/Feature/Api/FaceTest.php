<?php

use App\Models\FaceEnrollment;
use App\Models\User;

function faceDescriptors(int $count = 3): array
{
    $descriptors = [];

    for ($i = 0; $i < $count; $i++) {
        $descriptors[] = array_fill(0, 128, 0.1);
    }

    return $descriptors;
}

test('face enrollment requires authentication', function () {
    $this->getJson('/api/face/enrollment')->assertUnauthorized();
    $this->postJson('/api/face/enroll', ['descriptors' => faceDescriptors()])->assertUnauthorized();
});

test('user can enroll face descriptors and sees them back', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/face/enroll', ['user_id' => $user->id, 'descriptors' => faceDescriptors(3)])
        ->assertOk()
        ->assertJsonPath('enrolled', true);

    expect(FaceEnrollment::where('user_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($user)->getJson('/api/face/enrollment')
        ->assertOk()
        ->assertJsonPath('enrolled', true)
        ->assertJsonCount(3, 'descriptors');
});

test('enrollment requires at least three descriptor samples', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/face/enroll', ['descriptors' => faceDescriptors(2)])
        ->assertStatus(422);
});

test('enrollment upserts instead of duplicating', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/face/enroll', ['descriptors' => faceDescriptors()]);
    $this->actingAs($user)->postJson('/api/face/enroll', ['descriptors' => faceDescriptors()]);

    expect(FaceEnrollment::where('user_id', $user->id)->count())->toBe(1);
});

test('descriptors must be 128 floats', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/face/enroll', ['descriptors' => [[0.1, 0.2, 0.3]]])
        ->assertStatus(422);
});

test('enrollment stored encrypted at rest', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/face/enroll', ['descriptors' => faceDescriptors()]);

    $raw = DB::table('face_enrollments')->where('user_id', $user->id)->value('descriptors');

    expect($raw)->toBeString();
    expect($raw)->not->toContain('0.1');
});

test('user cannot read another enrollment', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    FaceEnrollment::create(['user_id' => $owner->id, 'descriptors' => faceDescriptors()]);

    $this->actingAs($intruder)->getJson('/api/face/enrollment')
        ->assertOk()
        ->assertJsonPath('enrolled', false);
});

test('user can remove their face enrollment', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/face/enroll', ['descriptors' => faceDescriptors()]);

    $this->actingAs($user)->deleteJson('/api/face/enrollment')->assertOk();

    expect(FaceEnrollment::where('user_id', $user->id)->exists())->toBeFalse();
});
