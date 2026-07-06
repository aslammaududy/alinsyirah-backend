<?php

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('exports students as xlsx', function () {
    Student::factory()->count(3)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/exports/students');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('exports students filtered by class', function () {
    Student::factory()->count(2)->create(['school_class' => 'X-A']);
    Student::factory()->count(3)->create(['school_class' => 'XI-B']);

    $response = $this->withToken($this->token)
        ->getJson('/api/exports/students?school_class=X-A');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('exports tuition invoices as xlsx', function () {
    $student = Student::factory()->create();
    TuitionInvoice::factory()->count(3)->create([
        'student_id' => $student->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/exports/tuition-invoices');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('exports payment records as xlsx', function () {
    $student = Student::factory()->create();
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'status' => 'paid',
        'created_by' => $this->user->id,
    ]);

    $attempt->invoices()->attach($invoice->id, [
        'allocated_amount' => $invoice->amount,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/exports/payments');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('rejects invalid export type', function () {
    $this->withToken($this->token)
        ->getJson('/api/exports/invalid')
        ->assertUnprocessable();
});
