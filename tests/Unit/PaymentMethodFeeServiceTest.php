<?php

use App\Services\PaymentMethodFeeService;

test('calculates qris fee as 0.7 percent of amount', function () {
    $service = new PaymentMethodFeeService;
    $result = $service->calculate('qris', 100000);

    expect($result['fee_amount'])->toBe(700);
    expect($result['fee_percentage'])->toBe(0.7);
});

test('calculates qris fee for larger amount', function () {
    $service = new PaymentMethodFeeService;
    $result = $service->calculate('qris', 300000);

    expect($result['fee_amount'])->toBe(2100);
    expect($result['fee_percentage'])->toBe(0.7);
});

test('calculates bank transfer fee as flat 4000', function () {
    $service = new PaymentMethodFeeService;
    $result = $service->calculate('bank_transfer', 100000);

    expect($result['fee_amount'])->toBe(4000);
    expect($result['fee_percentage'])->toBe(0);
});

test('bank transfer fee is same regardless of amount', function () {
    $service = new PaymentMethodFeeService;
    $result = $service->calculate('bank_transfer', 500000);

    expect($result['fee_amount'])->toBe(4000);
});

test('throws exception for unknown payment method', function () {
    $service = new PaymentMethodFeeService;
    $service->calculate('unknown', 100000);
})->throws(InvalidArgumentException::class, 'Unknown payment method: unknown');
