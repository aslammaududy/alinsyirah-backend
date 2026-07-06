<?php

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->student = Student::factory()->create();
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;

    Config::set('midtrans.server_key', 'SB-Mid-server-test-key');
    Config::set('midtrans.payment_link_url', 'https://api.sandbox.midtrans.com/v1/payment-links');

    Http::fake();
});

it('cancels a created payment attempt and reverts invoices to draft', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
        'generation_source' => 'manual',
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'status' => 'created',
        'provider_response' => ['id' => 'PL-12345'],
    ]);

    $attempt->invoices()->attach($invoice->id, ['allocated_amount' => $invoice->amount]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertOk()
        ->assertJsonFragment(['status' => 'cancelled']);

    $attempt->refresh();
    expect($attempt->status)->toBe('cancelled');
    expect($attempt->payment_url)->toBeNull();

    $invoice->refresh();
    expect($invoice->status)->toBe('draft');
});

it('rejects cancelling a paid payment attempt', function () {
    $attempt = PaymentAttempt::factory()->create([
        'status' => 'paid',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertStatus(422);
});

it('rejects cancelling a cancelled payment attempt (already terminal)', function () {
    $attempt = PaymentAttempt::factory()->create([
        'status' => 'cancelled',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertStatus(422);
});

it('rejects cancelling an expired payment attempt (already terminal)', function () {
    $attempt = PaymentAttempt::factory()->create([
        'status' => 'expired',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertStatus(422);
});

it('deletes annual_prepayment invoices when cancelling', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
        'generation_source' => 'annual_prepayment',
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'status' => 'created',
        'provider_response' => ['id' => 'PL-67890'],
    ]);

    $attempt->invoices()->attach($invoice->id, ['allocated_amount' => $invoice->amount]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertOk();

    expect(TuitionInvoice::where('id', $invoice->id)->exists())->toBeFalse();
});

it('reverts manual invoices to draft and deletes annual_prepayment invoices', function () {
    $manualInvoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
        'generation_source' => 'manual',
    ]);

    $annualInvoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
        'generation_source' => 'annual_prepayment',
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'status' => 'created',
        'provider_response' => ['id' => 'PL-mixed'],
    ]);

    $attempt->invoices()->attach([
        $manualInvoice->id => ['allocated_amount' => $manualInvoice->amount],
        $annualInvoice->id => ['allocated_amount' => $annualInvoice->amount],
    ]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertOk();

    $manualInvoice->refresh();
    expect($manualInvoice->status)->toBe('draft');

    expect(TuitionInvoice::where('id', $annualInvoice->id)->exists())->toBeFalse();
});

it('deactivates midtrans payment link on cancel', function () {
    $invoice = TuitionInvoice::factory()->pendingPayment()->create([
        'student_id' => $this->student->id,
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'status' => 'created',
        'provider_response' => ['id' => 'PL-deactivate-me'],
    ]);

    $attempt->invoices()->attach($invoice->id, ['allocated_amount' => $invoice->amount]);

    $this->withToken($this->token)
        ->postJson("/api/payment-attempts/{$attempt->id}/cancel")
        ->assertOk();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sandbox.midtrans.com/v1/payment-links/PL-deactivate-me'
            && $request->method() === 'DELETE';
    });
});
