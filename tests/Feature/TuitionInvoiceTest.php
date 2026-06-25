<?php

use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->student = Student::factory()->create(['monthly_fee' => 200000]);

    Config::set('midtrans.server_key', 'SB-Mid-server-test-key');
    Config::set('midtrans.payment_link_url', 'https://api.sandbox.midtrans.com/v1/payment-links');
});

it('prevents payment on a terminal invoice', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'paid',
    ]);

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Http::fake();

    $this->withToken($token)
        ->postJson("/api/tuition-invoices/{$invoice->id}/pay")
        ->assertStatus(422);
});

it('prevents payment on a draft invoice that has a non-terminal attempt', function () {
    $invoice = TuitionInvoice::factory()->create([
        'student_id' => $this->student->id,
        'status' => 'paid',
    ]);

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/tuition-invoices/{$invoice->id}/pay")
        ->assertStatus(422);
});
