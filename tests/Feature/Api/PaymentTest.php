<?php

use App\Models\Fee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('can list payments', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/payments');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('payments ledger is read-only for students', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/api/payments', [
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'fee_type' => 'fee',
        'amount' => 100,
        'payment_method' => 'cash',
        'status' => 'pending',
    ])->assertStatus(405);

    $this->actingAs($user)->putJson("/api/payments/{$payment->id}", [
        'status' => 'paid',
    ])->assertStatus(405);

    $this->actingAs($user)->deleteJson("/api/payments/{$payment->id}")->assertStatus(405);
});

test('can show own payment', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/payments/{$payment->id}");

    $response->assertStatus(200);
});

test('cannot show another user\u{2019}s payment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson("/api/payments/{$payment->id}");

    $response->assertStatus(404);
});

test('payment list is scoped to the student', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $own = Payment::factory()->create(['user_id' => $user->id]);
    Payment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson('/api/payments');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('can get payment receipt', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id, 'status' => 'paid']);
    Receipt::factory()->create(['payment_id' => $payment->id]);

    $response = $this->actingAs($user)->getJson("/api/payments/{$payment->id}/receipt");

    $response->assertStatus(200);
});

test('can list receipts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $payment = Payment::factory()->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->create(['payment_id' => $payment->id]);

    $otherPayment = Payment::factory()->create(['user_id' => $other->id]);
    Receipt::factory()->create(['payment_id' => $otherPayment->id]);

    $response = $this->actingAs($user)->getJson('/api/receipts');

    $response->assertStatus(200)
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.receipt_number', $receipt->receipt_number);
});

test('can show own receipt', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->create(['payment_id' => $payment->id]);

    $response = $this->actingAs($user)->getJson("/api/receipts/{$receipt->id}");

    $response->assertStatus(200);
});

test('cannot show another student\u{2019}s receipt', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $other->id]);
    $receipt = Receipt::factory()->create(['payment_id' => $payment->id]);

    $response = $this->actingAs($user)->getJson("/api/receipts/{$receipt->id}");

    $response->assertStatus(404);
});
