<?php

use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('midtrans.server_key', 'SB-Mid-server-test-key');
    Config::set('midtrans.core_api_base_url', 'https://api.sandbox.midtrans.com');
});

test('createcharge builds correct payload for qris', function () {
    Http::fake([
        '*/v2/charge' => Http::response([
            'status_code' => '200',
            'status_message' => 'Success',
            'transaction_id' => 'txn-123',
            'order_id' => 'ORD-123',
            'gross_amount' => '100700',
            'payment_type' => 'qris',
            'actions' => [
                [
                    'name' => 'generate-qr',
                    'method' => 'GET',
                    'url' => 'https://midtrans-gg.qr-id.com/qr-123',
                ],
            ],
        ], 200),
    ]);

    $service = new MidtransService;
    $result = $service->createCharge([
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => 'ORD-123',
            'gross_amount' => 100700,
        ],
        'item_details' => [
            ['id' => 'spp', 'price' => 100000, 'quantity' => 1, 'name' => 'SPP Fee'],
            ['id' => 'fee', 'price' => 700, 'quantity' => 1, 'name' => 'Biaya Admin QRIS (0.7%)'],
        ],
        'customer_details' => [
            'first_name' => 'Test',
            'email' => 'test@example.com',
        ],
    ]);

    expect($result['payment_type'])->toBe('qris');
    expect($result['actions'][0]['url'])->toBe('https://midtrans-gg.qr-id.com/qr-123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request->data()['payment_type'] === 'qris';
    });
});

test('createcharge builds correct payload for bank transfer', function () {
    Http::fake([
        '*/v2/charge' => Http::response([
            'status_code' => '200',
            'status_message' => 'Success',
            'transaction_id' => 'txn-456',
            'order_id' => 'ORD-456',
            'gross_amount' => '104000',
            'payment_type' => 'bank_transfer',
            'va_numbers' => [
                [
                    'va_number' => '1234567890123',
                    'bank' => 'bca',
                ],
            ],
        ], 200),
    ]);

    $service = new MidtransService;
    $result = $service->createCharge([
        'payment_type' => 'bank_transfer',
        'bsi_va' => ['va_number' => null],
        'transaction_details' => [
            'order_id' => 'ORD-456',
            'gross_amount' => 104000,
        ],
        'item_details' => [
            ['id' => 'spp', 'price' => 100000, 'quantity' => 1, 'name' => 'SPP Fee'],
            ['id' => 'fee', 'price' => 4000, 'quantity' => 1, 'name' => 'Biaya Admin Transfer Bank'],
        ],
        'customer_details' => [
            'first_name' => 'Test',
            'email' => 'test@example.com',
        ],
    ]);

    expect($result['payment_type'])->toBe('bank_transfer');
    expect($result['va_numbers'][0]['va_number'])->toBe('1234567890123');
    expect($result['va_numbers'][0]['bank'])->toBe('bca');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request->data()['bsi_va']['va_number'] === null;
    });
});

test('createcharge throws exception on failure', function () {
    Http::fake([
        '*/v2/charge' => Http::response([
            'status_code' => '400',
            'status_message' => 'Bad Request',
        ], 400),
    ]);

    $service = new MidtransService;
    $service->createCharge([
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => 'ORD-789',
            'gross_amount' => 100000,
        ],
        'item_details' => [],
        'customer_details' => [
            'first_name' => 'Test',
            'email' => 'test@example.com',
        ],
    ]);
})->throws(RuntimeException::class);
