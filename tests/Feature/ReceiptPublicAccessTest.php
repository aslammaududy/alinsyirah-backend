<?php

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->student = Student::factory()->create();
    $this->invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    $this->attempt = PaymentAttempt::factory()->create(['status' => 'paid']);

    $this->attempt->invoices()->attach($this->invoice->id, [
        'allocated_amount' => $this->invoice->amount,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('allows access with valid signed URL', function () {
    $url = URL::temporarySignedRoute(
        'receipt.public',
        now()->addDays(7),
        ['payment_attempt' => $this->attempt->id],
    );

    $this->get($url)
        ->assertOk()
        ->assertSee($this->student->name);
});

it('rejects expired signed URL', function () {
    $url = URL::temporarySignedRoute(
        'receipt.public',
        now()->subHour(),
        ['payment_attempt' => $this->attempt->id],
    );

    $this->get($url)
        ->assertForbidden();
});

it('rejects tampered signed URL', function () {
    $url = URL::temporarySignedRoute(
        'receipt.public',
        now()->addDays(7),
        ['payment_attempt' => $this->attempt->id],
    );

    // Tamper with the URL by modifying the signature
    $tamperedUrl = preg_replace('/signature=[a-f0-9]+/', 'signature=00000000000000000000000000000000', $url);

    $this->get($tamperedUrl)
        ->assertForbidden();
});
