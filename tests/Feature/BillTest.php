<?php

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

it('returns bill HTML for a pending_payment invoice', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/invoices/{$invoice->id}/bill");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($invoice->description)
        ->assertSee($invoice->period);
});

it('returns bill PDF download for a pending_payment invoice', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/invoices/{$invoice->id}/bill/download");

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('rejects bill for a paid invoice', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->withToken($this->token)
        ->getJson("/api/invoices/{$invoice->id}/bill")
        ->assertNotFound();
});

it('requires authentication', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
    ]);

    $this->getJson("/api/invoices/{$invoice->id}/bill")
        ->assertUnauthorized();
});
