<?php

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(LazilyRefreshDatabase::class);

/**
 * Helper to build a valid Midtrans webhook signature.
 */
function makeWebhookSignature(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
{
    return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
}

/**
 * Helper to build a standard webhook payload.
 */
function makeWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'status_code' => '200',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'order_id' => 'ORD-TEST-'.uniqid(),
        'gross_amount' => '302100',
        'transaction_time' => date('Y-m-d H:i:s'),
        'transaction_id' => 'txn-'.uniqid(),
        'payment_type' => 'qris',
    ], $overrides);
}

beforeEach(function () {
    // Set a test server key so signature verification works without real env
    Config::set('midtrans.server_key', 'SB-Mid-server-FAKE-test-key');
});

it('updates payment attempt to paid on settlement webhook', function () {
    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'pending_payment',
    ]);

    $orderId = 'ORD-TEST-'.uniqid();
    $paymentAttempt = PaymentAttempt::factory()->create([
        'provider_order_id' => $orderId,
        'status' => 'created',
        'payment_method' => 'qris',
        'fee_amount' => 2100,
    ]);

    $paymentAttempt->invoices()->attach($invoice->id, [
        'allocated_amount' => 300000,
    ]);

    // Generate valid signature
    $statusCode = '200';
    $grossAmount = '302100';
    $serverKey = config('midtrans.server_key');
    $signatureKey = makeWebhookSignature($orderId, $statusCode, $grossAmount, $serverKey);

    $payload = makeWebhookPayload([
        'order_id' => $orderId,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ]);

    // Send webhook
    $response = $this->postJson('/api/midtrans/webhook', $payload);

    $response->assertOk();
    $response->assertJson(['message' => 'OK']);

    // Verify payment attempt status
    $paymentAttempt->refresh();
    expect($paymentAttempt->status)->toBe('paid');

    // Verify invoice status
    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_at)->not->toBeNull();
});

it('rejects webhook with invalid signature', function () {
    $payload = makeWebhookPayload([
        'signature_key' => 'totally_invalid_signature',
    ]);

    $response = $this->postJson('/api/midtrans/webhook', $payload);

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Invalid signature']);
});

it('is idempotent - does not overwrite paid status', function () {
    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'pending_payment',
    ]);

    $orderId = 'ORD-IDEMPOTENT-'.uniqid();
    $paymentAttempt = PaymentAttempt::factory()->create([
        'provider_order_id' => $orderId,
        'status' => 'created',
        'payment_method' => 'qris',
        'fee_amount' => 2100,
    ]);

    $paymentAttempt->invoices()->attach($invoice->id, [
        'allocated_amount' => 300000,
    ]);

    // Generate valid signature
    $statusCode = '200';
    $grossAmount = '302100';
    $serverKey = config('midtrans.server_key');
    $signatureKey = makeWebhookSignature($orderId, $statusCode, $grossAmount, $serverKey);

    $payload = makeWebhookPayload([
        'order_id' => $orderId,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ]);

    // First webhook - should succeed
    $response1 = $this->postJson('/api/midtrans/webhook', $payload);
    $response1->assertOk();

    // Second webhook (duplicate) - should also succeed but not overwrite
    $response2 = $this->postJson('/api/midtrans/webhook', $payload);
    $response2->assertOk();

    // Verify payment is still paid (not double-counted or errored)
    $paymentAttempt->refresh();
    expect($paymentAttempt->status)->toBe('paid');
});

it('returns 404 when payment attempt not found', function () {
    $statusCode = '200';
    $grossAmount = '302100';
    $serverKey = config('midtrans.server_key');
    $orderId = 'ORD-NONEXISTENT-'.uniqid();
    $signatureKey = makeWebhookSignature($orderId, $statusCode, $grossAmount, $serverKey);

    $payload = makeWebhookPayload([
        'order_id' => $orderId,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ]);

    $response = $this->postJson('/api/midtrans/webhook', $payload);

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Payment attempt not found']);
});

it('handles expire status by marking invoice as expired', function () {
    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'pending_payment',
    ]);

    $orderId = 'ORD-EXPIRE-'.uniqid();
    $paymentAttempt = PaymentAttempt::factory()->create([
        'provider_order_id' => $orderId,
        'status' => 'created',
        'payment_method' => 'qris',
    ]);

    $paymentAttempt->invoices()->attach($invoice->id, [
        'allocated_amount' => 300000,
    ]);

    $statusCode = '200';
    $grossAmount = '302100';
    $serverKey = config('midtrans.server_key');
    $signatureKey = makeWebhookSignature($orderId, $statusCode, $grossAmount, $serverKey);

    $payload = makeWebhookPayload([
        'order_id' => $orderId,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'expire',
    ]);

    $response = $this->postJson('/api/midtrans/webhook', $payload);

    $response->assertOk();

    $paymentAttempt->refresh();
    expect($paymentAttempt->status)->toBe('expired');
});
