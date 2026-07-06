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
    $this->student = Student::factory()->create();
});

it('returns receipt HTML for a paid payment attempt', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
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
        ->getJson("/api/payment-attempts/{$attempt->id}/receipt");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($this->student->name);
});

it('returns receipt PDF download for a paid payment attempt', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
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
        ->getJson("/api/payment-attempts/{$attempt->id}/receipt/download");

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('returns signed share URL', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
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
        ->getJson("/api/payment-attempts/{$attempt->id}/receipt/share");

    $response->assertOk()
        ->assertJsonStructure(['url', 'download_url', 'expires_at']);
});

it('rejects receipt for a non-paid attempt', function () {
    $attempt = PaymentAttempt::factory()->create([
        'status' => 'created',
        'created_by' => $this->user->id,
    ]);

    $this->withToken($this->token)
        ->getJson("/api/payment-attempts/{$attempt->id}/receipt")
        ->assertNotFound();
});

it('requires authentication', function () {
    $attempt = PaymentAttempt::factory()->create([
        'status' => 'paid',
    ]);

    $this->getJson("/api/payment-attempts/{$attempt->id}/receipt")
        ->assertUnauthorized();
});
