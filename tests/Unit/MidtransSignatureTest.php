<?php

use App\Services\MidtransService;

beforeEach(function () {
    $this->service = (new MidtransService)->setServerKey('SB-Mid-server-test-key-for-signature');

    $this->orderId = 'PL-TEST-ORDER-123';
    $this->statusCode = '200';
    $this->grossAmount = '100000.00';
    $this->serverKey = 'SB-Mid-server-test-key-for-signature';
});

it('verifies a valid SHA512 signature', function () {
    $validSignature = hash('sha512',
        $this->orderId.$this->statusCode.$this->grossAmount.$this->serverKey
    );

    expect($this->service->verifySignature(
        $this->orderId, $this->statusCode, $this->grossAmount, $validSignature
    ))->toBeTrue();
});

it('rejects an invalid SHA512 signature', function () {
    expect($this->service->verifySignature(
        'PL-TEST123', '200', '100000.00', 'invalid-signature'
    ))->toBeFalse();
});

it('rejects a signature computed with a different server key', function () {
    $computedWithWrongKey = hash('sha512',
        $this->orderId.$this->statusCode.$this->grossAmount.'SB-Mid-server-wrong-key'
    );

    expect($this->service->verifySignature(
        $this->orderId, $this->statusCode, $this->grossAmount, $computedWithWrongKey
    ))->toBeFalse();
});

it('generates a unique order id each time', function () {
    $id1 = $this->service->generateOrderId();
    $id2 = $this->service->generateOrderId();

    expect($id1)->not->toBe($id2);
    expect($id1)->toMatch('/^ORD-[A-F0-9]{24}$/');
});
