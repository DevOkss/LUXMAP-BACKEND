<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Program;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\AcademicTermSeeder;
use Database\Seeders\InstituteSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed([
        InstituteSeeder::class,
        OrganizationSeeder::class,
        AcademicTermSeeder::class,
    ]);
});

function studentImportAdmin(): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'position' => 'System Administrator',
        'assigned_at' => now(),
    ]);

    return $user;
}

/**
 * @param  array<int, array<int, mixed>>  $rows
 * @param  array<int, string>|null  $headers
 */
function studentImportFile(array $rows, ?array $headers = null): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers ??= [
        'student_number', 'name', 'email', 'phone',
        'year_level', 'sex', 'is_enrolled', 'institute', 'program',
    ];

    $sheet->fromArray($headers, null, 'A1');

    foreach ($rows as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2));
    }

    $path = tempnam(sys_get_temp_dir(), 'student-import-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile(
        $path,
        'students.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

test('super admin can view the student import page', function () {
    $admin = studentImportAdmin();

    $this->actingAs($admin)->get('/admin/students/import')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/Import')
            ->where('tempPassword', 'student')
            ->has('templateUrl'));
});

test('heads cannot access the student import', function () {
    $head = User::factory()->create();
    $head->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => UserRole::SSC_HEAD->value,
        'position' => 'Head',
        'assigned_at' => now(),
    ]);

    $this->actingAs($head)->get('/admin/students/import')->assertForbidden();
    $this->actingAs($head)->get('/admin/students/import/template')->assertForbidden();
    $this->actingAs($head)->post('/admin/students/import', [
        'file' => studentImportFile([]),
    ])->assertForbidden();
});

test('super admin can download the import template', function () {
    $admin = studentImportAdmin();

    $response = $this->actingAs($admin)->get('/admin/students/import/template');

    $response->assertOk();
    $response->assertDownload('student-import-template.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'student-template-').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $spreadsheet = IOFactory::load($path);
    $students = $spreadsheet->getSheetByName('Students');

    expect($students)->not->toBeNull();
    expect($students->rangeToArray('A1:I1')[0])->toBe([
        'student_number', 'name', 'email', 'phone',
        'year_level', 'sex', 'is_enrolled', 'institute', 'program',
    ]);
    expect($spreadsheet->getSheetByName('Instructions'))->not->toBeNull();

    unlink($path);
});

test('super admin imports a new student with a temporary password and enrollment snapshot', function () {
    $admin = studentImportAdmin();

    $response = $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            ['2026-00001', 'Juan Dela Cruz', 'JUAN@EXAMPLE.COM', '09171234567', 1, 'M', 1, 'ICS', 'BSCS'],
        ]),
    ]);

    $response->assertRedirect(route('admin.students.import.create'));
    $response->assertSessionHas('import_result');

    $result = session('import_result');

    expect($result['created'])->toBe(1);
    expect($result['updated'])->toBe(0);
    expect($result['failed'])->toBe(0);

    $user = User::where('student_number', '2026-00001')->firstOrFail();

    expect($user->name)->toBe('Juan Dela Cruz');
    expect($user->email)->toBe('juan@example.com');
    expect($user->phone)->toBe('09171234567');
    expect($user->year_level)->toBe(1);
    expect($user->sex)->toBe('M');
    expect($user->is_enrolled)->toBeTrue();
    expect($user->institute_id)->toBe(Institute::where('code', 'ICS')->value('id'));
    expect($user->program_id)->toBe(Program::where('code', 'BSCS')->value('id'));
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('student', $user->password))->toBeTrue();

    $term = AcademicTerm::active()->firstOrFail();
    $enrollment = $user->enrollmentForTerm($term);

    expect($enrollment)->not->toBeNull();
    expect($enrollment->institute_id)->toBe($user->institute_id);
    expect($enrollment->program_id)->toBe($user->program_id);
    expect($enrollment->year_level)->toBe(1);
    expect($enrollment->is_enrolled)->toBeTrue();
});

test('import resolves institute and program by their full names', function () {
    $admin = studentImportAdmin();

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            [
                '2026-00002',
                'Maria Santos',
                'maria@example.com',
                '',
                2,
                'Female',
                'Yes',
                'Institute of Computer Studies',
                'Bachelor of Science in Computer Science',
            ],
        ]),
    ])->assertRedirect();

    $user = User::where('student_number', '2026-00002')->firstOrFail();

    expect($user->institute_id)->toBe(Institute::where('code', 'ICS')->value('id'));
    expect($user->program_id)->toBe(Program::where('code', 'BSCS')->value('id'));
    expect($user->year_level)->toBe(2);
    expect($user->sex)->toBe('F');
    expect($user->is_enrolled)->toBeTrue();
});

test('import updates an existing student without changing the password or payments', function () {
    $admin = studentImportAdmin();
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();
    $ias = Institute::where('code', 'IAS')->firstOrFail();
    $abPolSci = Program::where('code', 'AB PolSci')->firstOrFail();
    $term = AcademicTerm::active()->firstOrFail();

    $user = User::factory()->create([
        'student_number' => '2026-00003',
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => bcrypt('my-secret-password'),
        'must_change_password' => false,
        'is_enrolled' => true,
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
        'year_level' => 1,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'organization_id' => Organization::where('code', 'SSC')->value('id'),
    ]);

    StudentEnrollment::create([
        'user_id' => $user->id,
        'academic_term_id' => $term->id,
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
        'year_level' => 1,
        'is_enrolled' => true,
    ]);

    $historicalTerm = AcademicTerm::factory()->create([
        'academic_year' => '2025-2026',
        'semester' => '1st',
        'is_active' => false,
    ]);
    $historicalEnrollment = StudentEnrollment::create([
        'user_id' => $user->id,
        'academic_term_id' => $historicalTerm->id,
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
        'year_level' => 1,
        'is_enrolled' => true,
    ]);

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            ['2026-00003', 'New Name', 'new@example.com', '09991234567', 2, 'M', 1, 'IAS', 'AB PolSci'],
        ]),
    ])->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->phone)->toBe('09991234567');
    expect($user->year_level)->toBe(2);
    expect($user->sex)->toBe('M');
    expect($user->institute_id)->toBe($ias->id);
    expect($user->program_id)->toBe($abPolSci->id);
    expect($user->must_change_password)->toBeFalse();
    expect(Hash::check('my-secret-password', $user->password))->toBeTrue();

    expect(Payment::find($payment->id))->not->toBeNull();

    $current = $user->enrollmentForTerm($term);

    expect($current->institute_id)->toBe($ias->id);
    expect($current->program_id)->toBe($abPolSci->id);
    expect($current->year_level)->toBe(2);

    expect($historicalEnrollment->fresh()->program_id)->toBe($bscs->id);
});

test('import restores a soft deleted student', function () {
    $admin = studentImportAdmin();
    $user = User::factory()->create(['student_number' => '2026-00004']);
    $user->delete();

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            ['2026-00004', 'Restored Student', 'restored@example.com', '', 1, 'F', 1, 'ICS', 'BSCS'],
        ]),
    ])->assertRedirect();

    $user = User::withTrashed()->where('student_number', '2026-00004')->firstOrFail();

    expect($user->trashed())->toBeFalse();
    expect($user->name)->toBe('Restored Student');
});

test('import reports row errors without stopping valid rows', function () {
    $admin = studentImportAdmin();

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            ['', 'Missing Number', 'missing@example.com', '', 1, 'M', 1, 'ICS', 'BSCS'],
            ['2026-00005', 'Unknown Institute', 'unknown@example.com', '', 1, 'M', 1, 'NOPE', 'BSCS'],
            ['2026-00006', 'Invalid Email', 'not-an-email', '', 1, 'M', 1, 'ICS', 'BSCS'],
            ['2026-00007', 'Valid Student', 'valid@example.com', '', 1, 'M', 1, 'ICS', 'BSCS'],
            ['2026-00007', 'Duplicate Student', 'duplicate@example.com', '', 1, 'M', 1, 'ICS', 'BSCS'],
        ]),
    ])->assertRedirect();

    $result = session('import_result');

    expect($result['created'])->toBe(1);
    expect($result['failed'])->toBe(4);
    expect($result['errors'])->toHaveCount(4);
    expect(User::where('student_number', '2026-00007')->exists())->toBeTrue();
});

test('import refuses to overwrite a staff account', function () {
    $admin = studentImportAdmin();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    $head = User::factory()->create([
        'student_number' => '2026-00008',
        'name' => 'Staff Member',
    ]);
    $head->organizations()->attach($ssc->id, [
        'role' => UserRole::SSC_HEAD->value,
        'position' => 'Head',
        'assigned_at' => now(),
    ]);

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile([
            ['2026-00008', 'Overwritten', 'overwritten@example.com', '', 1, 'M', 1, 'ICS', 'BSCS'],
        ]),
    ])->assertRedirect();

    $result = session('import_result');

    expect($result['failed'])->toBe(1);
    expect($head->fresh()->name)->toBe('Staff Member');
});

test('import rejects a file with missing columns', function () {
    $admin = studentImportAdmin();

    $this->actingAs($admin)->post('/admin/students/import', [
        'file' => studentImportFile(
            [['2026-00009', 'No Email Column', '', 1, 'M', 1, 'ICS', 'BSCS']],
            ['student_number', 'name', 'phone', 'year_level', 'sex', 'is_enrolled', 'institute', 'program']
        ),
    ])->assertSessionHasErrors('file');
});
