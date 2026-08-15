<?php

use App\Enums\UserRole;
use App\Models\InstitutionAccount;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
        \Database\Seeders\InstitutionAccountSeeder::class,
    ]);
});

test('existing user in the real database can login with id number and password', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::STUDENT->value,
        'assigned_at' => now(),
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.student_number', '2026-00001')
        ->assertJsonPath('user.needs_onboarding', false);
});

test('existing user cannot login with a wrong password', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'wrong-password',
    ])->assertStatus(422);
});

test('super admin cannot login on the student domain', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'assigned_at' => now(),
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'Super admins must sign in via the Admin Portal.');
});

test('officer can login on the student domain', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::SSC_OFFICER->value,
        'assigned_at' => now(),
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.student_number', '2026-00001')
        ->assertJsonPath('user.needs_onboarding', false);
});

test('not enrolled user cannot login', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => false,
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ])->assertStatus(422);
});

test('new student is registered from the institution api on first login', function () {
    $response = $this->postJson('/api/login', [
        'student_number' => '243242',
        'password' => '12345678',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.student_number', '243242')
        ->assertJsonPath('user.name', 'Hearty L. Abugatal')
        ->assertJsonPath('user.phone', null)
        ->assertJsonPath('user.year_level', 3)
        ->assertJsonPath('user.sex', null)
        ->assertJsonPath('user.email', null)
        ->assertJsonPath('user.needs_onboarding', true);

    $user = User::where('student_number', '243242')->first();

    expect($user)->not->toBeNull();
    expect(Hash::check('12345678', $user->password))->toBeTrue();
    expect(Crypt::decryptString($user->institution_password_enc))->toBe('12345678');
    expect($user->is_enrolled)->toBeTrue();

    // A per-term enrollment snapshot is created from the institution API.
    $enrollment = $user->currentEnrollment();

    expect($enrollment)->not->toBeNull();
    expect($enrollment->year_level)->toBe(3);
    expect($enrollment->is_enrolled)->toBeTrue();
    expect($enrollment->institute_id)->toBeNull();
    expect($enrollment->program_id)->toBeNull();
});

test('graduated student cannot login', function () {
    InstitutionAccount::create([
        'stud_id' => '2020-90001',
        'password' => '12345678',
        'stud_fname' => 'Graduated',
        'stud_lname' => 'Student',
        'stud_mname' => null,
        'stud_year' => 4,
        'is_graduated' => true,
        'is_enrolled' => false,
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2020-90001',
        'password' => '12345678',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'Graduated students cannot access the portal.');

    expect(User::where('student_number', '2020-90001')->exists())->toBeFalse();
});

test('login fails with invalid institution credentials', function () {
    $response = $this->postJson('/api/login', [
        'student_number' => '243242',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'The provided credentials are incorrect.');
});

test('student completes onboarding by confirming institute and program', function () {
    $this->postJson('/api/login', [
        'student_number' => '243242',
        'password' => '12345678',
    ])->assertStatus(200);

    $user = User::where('student_number', '243242')->first();

    Sanctum::actingAs($user);

    $this->getJson('/api/onboarding')
        ->assertStatus(200)
        ->assertJsonPath('needs_onboarding', true)
        ->assertJsonPath('institutes.ICS', 'Institute of Computer Studies')
        ->assertJsonPath('programs.ICS.0', 'BSCS');

    $this->patchJson('/api/onboarding', [
        'institute' => 'ICS',
        'program' => 'BSCS',
    ])->assertStatus(200)
        ->assertJsonPath('user.institute', 'Institute of Computer Studies')
        ->assertJsonPath('user.program', 'Bachelor of Science in Computer Science')
        ->assertJsonPath('user.needs_onboarding', false);

    $user = $user->fresh();

    expect($user->is_enrolled)->toBeTrue();
    expect($user->institute_id)->toBe(Institute::where('code', 'ICS')->first()->id);
    expect($user->program_id)->toBe(Program::where('code', 'BSCS')->first()->id);

    // Onboarding writes the per-term enrollment snapshot, not historical rows.
    $enrollment = $user->currentEnrollment();

    expect($enrollment)->not->toBeNull();
    expect($enrollment->institute_id)->toBe($user->institute_id);
    expect($enrollment->program_id)->toBe($user->program_id);
});

test('onboarding returns institutes and programs from the database', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    Sanctum::actingAs($user);

    $this->getJson('/api/onboarding')
        ->assertStatus(200)
        ->assertJsonPath('institutes.IAS', 'Institute of Arts and Sciences')
        ->assertJsonPath('programs.IAS', ['AB English', 'AB PolSci', 'AB Communication'])
        ->assertJsonPath('programs.IBFS.0', 'BSBA MM');
});

test('student can onboard into a program with spaces and gets its institute and program', function () {
    \App\Models\AcademicTerm::factory()->create();

    $user = User::factory()->create([
        'student_number' => '2026-10001',
        'password' => bcrypt('password123'),
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/onboarding', [
        'institute' => 'IAS',
        'program' => 'AB English',
    ])->assertStatus(200)
        ->assertJsonPath('user.institute', 'Institute of Arts and Sciences')
        ->assertJsonPath('user.program', 'Bachelor of Arts in English Language');

    $user = $user->fresh();

    expect($user->institute_id)->toBe(Institute::where('code', 'IAS')->first()->id);
    expect($user->program_id)->toBe(Program::where('code', 'AB English')->first()->id);
});

test('onboarding program must belong to the selected institute', function () {
    $user = User::factory()->create([
        'student_number' => '2026-10001',
        'password' => bcrypt('password123'),
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/onboarding', [
        'institute' => 'ICS',
        'program' => 'BSA',
    ])->assertStatus(422)
        ->assertJsonPath('errors.program.0', 'The selected program does not belong to the selected institute.');
});

test('onboarding email is optional but validated when provided', function () {
    User::factory()->create(['email' => 'taken@test.com']);
    $user = User::factory()->create([
        'student_number' => '2026-10001',
        'password' => bcrypt('password123'),
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/onboarding', [
        'institute' => 'ICS',
        'program' => 'BSCS',
        'email' => 'taken@test.com',
    ])->assertStatus(422);
});

test('login refresh syncs existing user data from the institution api', function () {
    $user = User::factory()->create([
        'student_number' => '243242',
        'name' => 'Old Name',
        'phone' => null,
        'year_level' => 1,
        'sex' => null,
        'password' => bcrypt('12345678'),
        'is_enrolled' => true,
    ]);

    $this->postJson('/api/login', [
        'student_number' => '243242',
        'password' => '12345678',
    ])->assertStatus(200);

    $user->refresh();

    expect($user->name)->toBe('Hearty L. Abugatal');
    expect($user->year_level)->toBe(3);
    expect($user->phone)->toBeNull();
    expect($user->sex)->toBeNull();
    expect(Crypt::decryptString($user->institution_password_enc))->toBe('12345678');
});

test('existing user can be refreshed from the institution api while logged in', function () {
    $user = User::factory()->create([
        'student_number' => '243242',
        'name' => 'Old Name',
        'phone' => null,
        'password' => bcrypt('12345678'),
        'institution_password_enc' => Crypt::encryptString('12345678'),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/me/refresh')
        ->assertStatus(200)
        ->assertJsonPath('synced', true)
        ->assertJsonPath('user.year_level', 3);

    expect($user->fresh()->name)->toBe('Hearty L. Abugatal');
});

test('profile refresh is a no-op when no institution password is stored', function () {
    $user = User::factory()->create([
        'student_number' => '2026-10001',
        'password' => bcrypt('password123'),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/me/refresh')
        ->assertStatus(200)
        ->assertJsonPath('synced', false);
});
