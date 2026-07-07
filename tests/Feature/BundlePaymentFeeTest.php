<?php

use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Config::set('midtrans.server_key', 'SB-Mid-server-test-key');
    Config::set('midtrans.core_api_base_url', 'https://api.sandbox.midtrans.com');
});

test('bundle payment with qris includes fee in response', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice1 = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'draft',
    ]);

    Http::fake([
        '*/v2/charge' => Http::response([
            'status_code' => '200',
            'status_message' => 'Success',
            'transaction_id' => 'txn-qris-1',
            'order_id' => 'ORD-qris-1',
            'gross_amount' => '302100',
            'payment_type' => 'qris',
            'actions' => [
                [
                    'name' => 'generate-qr',
                    'method' => 'GET',
                    'url' => 'https://midtrans-gg.qr-id.com/qr-test',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/payment-attempts/bundle', [
            'tuition_invoice_ids' => [$invoice1->id],
            'allocations' => [
                ['id' => $invoice1->id, 'allocated_amount' => 300000],
            ],
            'payment_method' => 'qris',
            'customer_details' => [
                'first_name' => 'Test',
                'email' => 'test@example.com',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_method', 'qris')
        ->assertJsonPath('data.fee_amount', 2100)
        ->assertJsonPath('data.qr_code_url', 'https://midtrans-gg.qr-id.com/qr-test');
});

test('bundle payment with bank transfer includes va number', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice1 = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'draft',
    ]);

    Http::fake([
        '*/v2/charge' => Http::response([
            'status_code' => '200',
            'status_message' => 'Success',
            'transaction_id' => 'txn-va-1',
            'order_id' => 'ORD-va-1',
            'gross_amount' => '304000',
            'payment_type' => 'bank_transfer',
            'va_numbers' => [
                [
                    'va_number' => '1234567890123',
                    'bank' => 'bca',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/payment-attempts/bundle', [
            'tuition_invoice_ids' => [$invoice1->id],
            'allocations' => [
                ['id' => $invoice1->id, 'allocated_amount' => 300000],
            ],
            'payment_method' => 'bank_transfer',
            'bank' => 'bsi',
            'customer_details' => [
                'first_name' => 'Test',
                'email' => 'test@example.com',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.fee_amount', 4000)
        ->assertJsonPath('data.va_number', '1234567890123')
        ->assertJsonPath('data.bank', 'bca');
});

test('bundle payment without payment method returns 422', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    $invoice1 = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/payment-attempts/bundle', [
            'tuition_invoice_ids' => [$invoice1->id],
            'allocations' => [
                ['id' => $invoice1->id, 'allocated_amount' => 300000],
            ],
            'customer_details' => [
                'first_name' => 'Test',
                'email' => 'test@example.com',
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});

test('bundle payment with bank transfer without bank returns 422', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    $invoice1 = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/payment-attempts/bundle', [
            'tuition_invoice_ids' => [$invoice1->id],
            'allocations' => [
                ['id' => $invoice1->id, 'allocated_amount' => 300000],
            ],
            'payment_method' => 'bank_transfer',
            'customer_details' => [
                'first_name' => 'Test',
                'email' => 'test@example.com',
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('bank');
});
