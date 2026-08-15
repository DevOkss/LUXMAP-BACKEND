<?php

use App\Models\Fee;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use App\Services\FeeService;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
    $this->service = app(FeeService::class);
});

function feeStudent(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'is_enrolled' => true,
        'year_level' => 1,
    ], $overrides));
}

test('publishing an SSC fee keeps it posted and eligible for all enrolled students', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    feeStudent();
    feeStudent(['year_level' => 3]);

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft', 'required_years' => ['all']]);

    $this->service->publish($fee);

    expect($fee->fresh()->status)->toBe('posted');
    expect($this->service->eligibleCount($fee->fresh()))->toBe(2);
});

test('ISC fee only covers students of the owning institute', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $ias = Institute::where('code', 'IAS')->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    $inIcs = feeStudent(['institute_id' => $ics->id, 'program_id' => Program::where('code', 'BSCS')->firstOrFail()->id]);
    feeStudent(['institute_id' => $ias->id]);

    $fee = Fee::factory()->create(['organization_id' => $isc->id, 'status' => 'posted']);

    expect($this->service->eligibleCount($fee->fresh()))->toBe(1);
    expect($this->service->studentObligations($inIcs)->pluck('id'))->toContain($fee->id);
});

test('SRO fee only covers only program students', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();
    $sro = Organization::where('code', 'BSCS-SRO')->firstOrFail();

    $inBscs = feeStudent(['institute_id' => $ics->id, 'program_id' => $bscs->id]);
    $other = feeStudent(['institute_id' => $ics->id, 'program_id' => Program::factory()->create(['institute_id' => $ics->id, 'code' => 'OTHER'])->id]);

    $fee = Fee::factory()->create(['organization_id' => $sro->id, 'status' => 'posted']);

    expect($this->service->eligibleCount($fee->fresh()))->toBe(1);
    expect($this->service->studentObligations($inBscs)->pluck('id'))->toContain($fee->id);
    expect($this->service->studentObligations($other)->pluck('id'))->not->toContain($fee->id);
});

test('required years restrict eligibility', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $first = feeStudent(['year_level' => 1]);
    $second = feeStudent(['year_level' => 2]);
    feeStudent(['year_level' => 4]);

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'posted', 'required_years' => ['1', '2']]);

    expect($this->service->eligibleCount($fee->fresh()))->toBe(2);
    expect($this->service->studentObligations($first)->count())->toBe(1);
    expect($this->service->studentObligations($second)->count())->toBe(1);
});

test('only posted fees appear as obligations', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = feeStudent();

    $draft = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);
    $posted = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'posted']);

    $obligations = $this->service->studentObligations($student);
    expect($obligations->pluck('id'))->toContain($posted->id);
    expect($obligations->pluck('id'))->not->toContain($draft->id);
});

test('a paid fee is no longer a due obligation', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = feeStudent();

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'posted']);

    Payment::factory()->fee($fee)->create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $obligation = $this->service->studentObligations($student)->first();
    expect($obligation->id)->toBe($fee->id);
    expect($obligation->obligation_status)->toBe('paid');
});

test('deleting a fee removes it as an obligation but keeps payment records', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = feeStudent();

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);
    $this->service->publish($fee);

    Payment::create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'fee_type' => 'fee',
        'fee_id' => $fee->id,
        'amount' => $fee->amount,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->service->delete($fee->id);

    expect(Fee::find($fee->id))->toBeNull();
    expect($this->service->studentObligations($student)->where('id', $fee->id))->toBeEmpty();
    expect(Payment::where('user_id', $student->id)->count())->toBe(1);
});