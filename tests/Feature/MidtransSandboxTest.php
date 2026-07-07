<?php

use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('can create QRIS charge in sandbox', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'draft',
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/tuition-invoices/{$invoice->id}/pay", [
            'payment_method' => 'qris',
        ]);

    $response->assertCreated();

    $data = $response->json('data');
    expect($data['payment_method'])->toBe('qris');
    expect($data['fee_amount'])->toBe(2100); // 0.7% of 300000
    expect($data['qr_code_url'])->not->toBeNull();
    expect($data['status'])->toBe('created');

    logger('QRIS charge response', $data);
});

it('can create BSI bank transfer charge in sandbox', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $student = Student::factory()->create(['monthly_fee' => 300000]);

    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $student->id,
        'period' => '2026-07',
        'amount' => 300000,
        'status' => 'draft',
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/tuition-invoices/{$invoice->id}/pay", [
            'payment_method' => 'bank_transfer',
            'bank' => 'bsi',
        ]);

    $response->assertCreated();

    $data = $response->json('data');
    expect($data['payment_method'])->toBe('bank_transfer');
    expect($data['fee_amount'])->toBe(4000);
    expect($data['va_number'])->not->toBeNull();
    expect($data['bank'])->toBe('bsi');
    expect($data['status'])->toBe('created');

    logger('BSI VA charge response', $data);
});
