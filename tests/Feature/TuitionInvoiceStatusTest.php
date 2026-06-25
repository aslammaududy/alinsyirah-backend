<?php

use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->student = Student::factory()->create();
});

it('transitions from draft to pending_payment', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'draft',
    ]);

    $invoice->transitionTo('pending_payment');

    expect($invoice->status)->toBe('pending_payment');
});

it('transitions from pending_payment to paid', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'pending_payment',
    ]);

    $invoice->transitionTo('paid');

    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_at)->not->toBeNull();
});

it('transitions from pending_payment to expired', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'pending_payment',
    ]);

    $invoice->transitionTo('expired');

    expect($invoice->status)->toBe('expired');
});

it('transitions from expired back to pending_payment', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'expired',
    ]);

    $invoice->transitionTo('pending_payment');

    expect($invoice->status)->toBe('pending_payment');
});

it('prevents transition from paid to any other status', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'paid',
    ]);

    expect(fn () => $invoice->transitionTo('pending_payment'))
        ->toThrow(InvalidArgumentException::class);
});

it('prevents transition from cancelled to any other status', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'cancelled',
    ]);

    expect(fn () => $invoice->transitionTo('draft'))
        ->toThrow(InvalidArgumentException::class);
});

it('detects terminal states correctly', function () {
    $paid = TuitionInvoice::factory()->create(['student_id' => $this->student->id, 'status' => 'paid']);
    $expired = TuitionInvoice::factory()->create(['student_id' => $this->student->id, 'status' => 'expired']);
    $cancelled = TuitionInvoice::factory()->create(['student_id' => $this->student->id, 'status' => 'cancelled']);
    $draft = TuitionInvoice::factory()->create(['student_id' => $this->student->id, 'status' => 'draft']);

    expect($paid->isTerminal())->toBeTrue();
    expect($expired->isTerminal())->toBeTrue();
    expect($cancelled->isTerminal())->toBeTrue();
    expect($draft->isTerminal())->toBeFalse();
});
