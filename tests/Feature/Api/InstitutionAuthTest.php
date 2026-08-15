<?php

use Database\Seeders\InstitutionAccountSeeder;

beforeEach(function () {
    $this->seed(InstitutionAccountSeeder::class);
});

test('institution endpoint returns student data with valid credentials', function () {
    $response = $this->postJson('/api/institution/auth', [
        'stud_id' => '243242',
        'password' => '12345678',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'StudID' => '243242',
            'StudFName' => 'Hearty',
            'StudLName' => 'Abugatal',
            'StudMName' => 'L',
            'StudYear' => 3,
            'isGraduated' => 0,
            'isEnrolled' => 1,
        ])
        ->assertJsonMissing(['password']);
});

test('institution endpoint rejects invalid credentials', function () {
    $response = $this->postJson('/api/institution/auth', [
        'stud_id' => '243242',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
});

test('institution endpoint rejects unknown student id', function () {
    $response = $this->postJson('/api/institution/auth', [
        'stud_id' => '2099-99999',
        'password' => '12345678',
    ]);

    $response->assertStatus(422);
});
