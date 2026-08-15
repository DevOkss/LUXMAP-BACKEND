<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\InstituteSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\TestCase;

beforeEach(function () {
    $this->seed([
        InstituteSeeder::class,
        OrganizationSeeder::class,
    ]);
});

function adminPaymentsUser(string $orgCode, UserRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function adminPaymentsStudent(): User
{
    return User::factory()->create(['is_enrolled' => true]);
}

function adminExportSheet(TestCase $test, User $officer, int $termId, array $extra = []): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $query = array_merge(['academic_term_id' => $termId], $extra);
    $response = $test->actingAs($officer)->get('/admin/payments/export?'.http_build_query($query));
    $response->assertOk();

    $path = sys_get_temp_dir().'/payments-export-helper-'.uniqid().'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        return \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
    } finally {
        @unlink($path);
    }
}

function adminCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, int $row)
{
    return $sheet->getCell([$col, $row])->getValue();
}

test('officer and head can access the payments module; super admin cannot', function () {
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);
    $superAdmin = adminPaymentsUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($officer)->get('/admin/payments')->assertOk();
    $this->actingAs($head)->get('/admin/payments')->assertOk();
    $this->actingAs($superAdmin)->get('/admin/payments')->assertForbidden();
});

test('payments index exposes academic terms and defaults to the current term', function () {
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);

    $this->actingAs($head)->get('/admin/payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('academic_terms', 1)
            ->where('academic_terms.0.id', $term->id)
            ->where('academic_terms.0.is_active', true)
            ->where('selected_term', $term->id));
});

test('payments index transactions are filtered by the selected academic term', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term1 = AcademicTerm::factory()->create(['academic_year' => '2025-2026', 'semester' => '1st', 'is_active' => false]);
    $term2 = AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'semester' => '2nd', 'is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);

    $student = adminPaymentsStudent();
    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term1->id,
        'fee_type' => 'fee',
        'amount' => 100,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term2->id,
        'fee_type' => 'fee',
        'amount' => 200,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($officer)->get('/admin/payments?academic_term_id='.$term1->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 1)
            ->where('transactions.0.total', 100)
            ->where('selected_term', $term1->id));
});

test('payments index transactions support search and date range filters', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);

    $student = adminPaymentsStudent();
    $student->update(['name' => 'Zed Zordinski', 'student_number' => '2026-0001']);
    $other = adminPaymentsStudent();
    $other->update(['name' => 'Ari Aardvark', 'student_number' => '2026-0002']);

    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 100,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
        'created_at' => now(),
    ]);
    Payment::factory()->create([
        'user_id' => $other->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 200,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);

    $this->actingAs($officer)->get('/admin/payments?search=2026-0001')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 1)
            ->where('transactions.0.total', 100));

    $this->actingAs($officer)->get('/admin/payments?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 1)
            ->where('transactions.0.total', 100));

    $this->actingAs($officer)->get('/admin/payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 2));
});

test('transactions are paginated and expose the full-scope total', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    foreach ([100, 200, 300] as $amount) {
        Payment::factory()->create([
            'user_id' => $student->id,
            'organization_id' => $ssc->id,
            'academic_term_id' => $term->id,
            'fee_type' => 'fee',
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    $this->actingAs($officer)->get('/admin/payments?per_page=1&page=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 1)
            ->where('transactions_total', 600)
            ->where('transactions_pagination.total', 3)
            ->where('transactions_pagination.current_page', 1)
            ->where('transactions_pagination.last_page', 3));
});

test('pending verifications are searchable, term-scoped, and paginated with totals', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $current = AcademicTerm::factory()->create(['is_active' => true]);
    $older = AcademicTerm::factory()->create(['academic_year' => '2024-2025', 'semester' => '1st', 'is_active' => false]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);

    $student = adminPaymentsStudent();
    $student->update(['name' => 'Zed Zordinski', 'student_number' => '2026-0001']);
    $other = adminPaymentsStudent();
    $other->update(['name' => 'Ari Aardvark', 'student_number' => '2026-0002']);

    $make = function (User $user, AcademicTerm $term, float $amount) use ($ssc) {
        $key = 'psub_'.fake()->uuid();
        PaymentSubmission::create([
            'user_id' => $user->id,
            'organization_id' => $ssc->id,
            'academic_term_id' => $term->id,
            'fee_type' => 'fee',
            'amount' => $amount,
            'payment_method' => 'cashless',
            'payment_channel' => 'gcash',
            'reference_number' => 'REF-'.fake()->numerify('####'),
            'group_key' => $key,
            'status' => 'pending',
            'lock_key' => PaymentSubmission::buildLockKey($user->id, $ssc->id, $term->id, 'fee', fake()->numberBetween(1, 999)),
        ]);
    };

    $make($student, $current, 150);
    $make($student, $current, 50);
    $make($other, $current, 80);
    $make($other, $older, 999);

    $this->actingAs($officer)->get('/admin/payments?tab=pending&pending_search=2026-0001')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('pending', 2)
            ->where('pending_search', '2026-0001')
            ->where('pending_total', 200)
            ->where('pending_pagination.total', 2));

    $this->actingAs($officer)->get('/admin/payments?tab=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('pending', 3)
            ->where('pending_total', 280)
            ->where('pending_pagination.total', 3));
});

test('outstanding aggregates balances across all enrolled students in scope and paginates', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);

    foreach (['2026-0001', '2026-0002'] as $number) {
        $student = adminPaymentsStudent();
        $student->update(['student_number' => $number]);
        StudentEnrollment::create([
            'user_id' => $student->id,
            'academic_term_id' => $term->id,
            'institute_id' => null,
            'program_id' => null,
            'year_level' => 1,
            'is_enrolled' => true,
        ]);
    }

    Fee::create([
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'name' => 'SSC Membership',
        'amount' => 500,
        'term' => $term->displayName(),
        'required_years' => ['all'],
        'due_date' => now()->addMonth(),
        'status' => 'posted',
    ]);

    $this->actingAs($officer)->get('/admin/payments?tab=outstanding&per_page=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('outstanding.students', 1)
            ->where('outstanding.total', 1000)
            ->where('outstanding.pagination.total', 2)
            ->where('outstanding_total', 1000));
});

test('transactions total excludes waived (exempted) amounts', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 75,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 200,
        'payment_method' => Payment::METHOD_EXEMPTION,
        'status' => Payment::STATUS_EXEMPTED,
        'isExempted' => true,
        'exempted_by' => $officer->id,
        'exempted_at' => now(),
        'notes' => 'Waived.',
    ]);

    $this->actingAs($officer)->get('/admin/payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 2)
            ->where('transactions_total', 75));
});

test('payment accounts are viewable by officers but managed only by heads', function () {
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $superAdmin = adminPaymentsUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($head)->get('/admin/payment-accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_manage', true));

    $this->actingAs($officer)->get('/admin/payment-accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_manage', false));

    $this->actingAs($officer)->post('/admin/payment-accounts', [
        'organization_id' => 1,
        'account_name' => 'SSC Official',
        'account_number' => '09171112222',
    ])->assertForbidden();

    $this->actingAs($superAdmin)->get('/admin/payment-accounts')->assertForbidden();
});

test('student obligations page exposes can_process for officers but not heads', function () {
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);
    $student = adminPaymentsStudent();

    $this->actingAs($officer)->get("/admin/payments/students/{$student->id}/obligations")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/StudentDetail')
            ->where('can_process', true));

    $this->actingAs($head)->get("/admin/payments/students/{$student->id}/obligations")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/StudentDetail')
            ->where('can_process', false));
});

test('submission detail verification is available to officers but not heads', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);
    $student = adminPaymentsStudent();

    $groupKey = 'psub_'.fake()->uuid();
    PaymentSubmission::create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 150,
        'payment_method' => 'cashless',
        'payment_channel' => 'gcash',
        'reference_number' => 'REF-001',
        'group_key' => $groupKey,
        'status' => 'pending',
        'lock_key' => PaymentSubmission::buildLockKey($student->id, $ssc->id, $term->id, 'fee', 1),
    ]);

    $this->actingAs($officer)->get("/admin/payments/submissions/{$groupKey}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/SubmissionDetail')
            ->where('can_verify', true));

    $this->actingAs($head)->get("/admin/payments/submissions/{$groupKey}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/SubmissionDetail')
            ->where('can_verify', false));
});

test('payments exempted in one session appear as a single transaction batch', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    $batch = (string) Str::uuid();
    foreach (['fee' => 500, 'penalty' => 100] as $type => $amount) {
        Payment::factory()->create([
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batch,
            'user_id' => $student->id,
            'organization_id' => $ssc->id,
            'academic_term_id' => $term->id,
            'fee_type' => $type,
            'amount' => $amount,
            'payment_method' => Payment::METHOD_EXEMPTION,
            'status' => Payment::STATUS_EXEMPTED,
            'isExempted' => true,
            'exempted_by' => $officer->id,
            'exempted_at' => now(),
            'notes' => 'Granted because student is an officer.',
        ]);
    }

    $this->actingAs($officer)->get('/admin/payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Index')
            ->has('transactions', 1)
            ->where('transactions.0.count', 2)
            ->where('transactions.0.isExempted', true)
            ->where('transactions.0.status', 'exempted')
            ->where('transactions.0.user.id', $student->id)
            ->where('transactions.0.items.0.fee_type', 'fee')
            ->where('transactions.0.items.1.fee_type', 'penalty'));
});

test('payment show page exposes the batch, exemption reason, and term history', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    $batch = (string) Str::uuid();
    $anchor = null;
    foreach (['fee' => 500, 'penalty' => 100] as $type => $amount) {
        $payment = Payment::factory()->create([
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batch,
            'user_id' => $student->id,
            'organization_id' => $ssc->id,
            'academic_term_id' => $term->id,
            'fee_type' => $type,
            'amount' => $amount,
            'payment_method' => Payment::METHOD_EXEMPTION,
            'status' => Payment::STATUS_EXEMPTED,
            'isExempted' => true,
            'exempted_by' => $officer->id,
            'exempted_at' => now(),
            'notes' => 'Granted because student is an officer.',
        ]);
        $anchor = $anchor ?: $payment;
    }

    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 250,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($officer)->get('/admin/payments/'.$anchor->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/Show')
            ->where('batch.count', 2)
            ->where('batch.isExempted', true)
            ->where('batch.notes', 'Granted because student is an officer.')
            ->where('batch.exemptedBy.name', $officer->name)
            ->where('student.id', $student->id)
            ->where('term', $term->displayName())
            ->has('history', 2));
});

test('officers and heads can export transactions; super admin cannot', function () {
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $head = adminPaymentsUser('SSC', UserRole::SSC_HEAD);
    $superAdmin = adminPaymentsUser('SSC', UserRole::SUPER_ADMIN);
    $term = AcademicTerm::factory()->create(['is_active' => true]);

    $this->actingAs($head)->get('/admin/payments/export?academic_term_id='.$term->id)
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeaderContains('content-disposition', '.xlsx');

    $this->actingAs($officer)->get('/admin/payments/export?academic_term_id='.$term->id)
        ->assertOk();

    $this->actingAs($superAdmin)->get('/admin/payments/export?academic_term_id='.$term->id)
        ->assertForbidden();
});

test('transactions export contains receipt numbers and highlights exempted rows', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    $paid = Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'amount' => 400,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    \App\Models\Receipt::create([
        'payment_id' => $paid->id,
        'receipt_number' => 'SOMS-20260101-0001',
        'issued_at' => now(),
    ]);

    $exempted = Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'penalty',
        'amount' => 100,
        'payment_method' => Payment::METHOD_EXEMPTION,
        'status' => Payment::STATUS_EXEMPTED,
        'isExempted' => true,
        'exempted_by' => $officer->id,
        'exempted_at' => now(),
        'notes' => 'Waived.',
        'created_at' => now()->addSeconds(1),
    ]);
    \App\Models\Receipt::create([
        'payment_id' => $exempted->id,
        'receipt_number' => 'SOMS-20260101-0002',
        'issued_at' => now(),
    ]);

    $response = $this->actingAs($officer)->get('/admin/payments/export?academic_term_id='.$term->id);
    $response->assertOk();

    $path = sys_get_temp_dir().'/payments-export-test-'.uniqid().'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

        $headers = ['RECEIPT NO.', 'DATE', 'STUDENT NAME', 'STUDENT NO.', 'ORGANIZATION', 'ACADEMIC TERM', 'TYPE', 'DESCRIPTION', 'AMOUNT', 'PAYMENT METHOD', 'REFERENCE NO.', 'STATUS', 'PROCESSED/EXEMPTED BY', 'NOTES'];
        foreach ($headers as $i => $header) {
            expect(adminCell($sheet, $i + 1, 1))->toBe($header);
        }

        expect($sheet->getHighestRow())->toBe(3);

        $receipts = [adminCell($sheet, 1, 2), adminCell($sheet, 1, 3)];
        expect($receipts)->toContain('SOMS-20260101-0001')->toContain('SOMS-20260101-0002');

        $statuses = [adminCell($sheet, 12, 2), adminCell($sheet, 12, 3)];
        expect($statuses)->toContain('Paid')->toContain('Exempted');

        $exemptedRow = collect([2, 3])->first(
            fn (int $r) => adminCell($sheet, 12, $r) === 'Exempted'
        );

        expect($sheet->getStyle([1, $exemptedRow, 14, $exemptedRow])->getFill()->getStartColor()->getARGB())->toBe('FFFEF3C7');
    } finally {
        @unlink($path);
    }
});

test('transactions export respects fee/penalty and individual item filters', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $officer = adminPaymentsUser('SSC', UserRole::SSC_OFFICER);
    $student = adminPaymentsStudent();

    $fee = \App\Models\Fee::create([
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'name' => 'SSC Membership',
        'amount' => 500,
        'term' => $term->displayName(),
        'required_years' => ['all'],
        'due_date' => now()->addMonth(),
        'status' => 'posted',
    ]);

    $event = \App\Models\Event::create([
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'title' => 'Foundation Day',
        'status' => 'completed',
        'event_date' => now(),
    ]);

    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'fee',
        'fee_id' => $fee->id,
        'amount' => 500,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'academic_term_id' => $term->id,
        'fee_type' => 'penalty',
        'event_id' => $event->id,
        'amount' => 100,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $feeSheet = adminExportSheet($this, $officer, $term->id, ['include_fees' => 1, 'include_penalties' => 0]);
    expect($feeSheet->getHighestRow())->toBe(2)
        ->and(adminCell($feeSheet, 8, 2))->toBe('SSC Membership');

    $penaltySheet = adminExportSheet($this, $officer, $term->id, ['include_fees' => 0, 'include_penalties' => 1]);
    expect($penaltySheet->getHighestRow())->toBe(2)
        ->and(adminCell($penaltySheet, 8, 2))->toBe('Foundation Day');

    $specificSheet = adminExportSheet($this, $officer, $term->id, ['include_penalties' => 0, 'fee_ids' => $fee->id]);
    expect($specificSheet->getHighestRow())->toBe(2)
        ->and(adminCell($specificSheet, 8, 2))->toBe('SSC Membership');
});
