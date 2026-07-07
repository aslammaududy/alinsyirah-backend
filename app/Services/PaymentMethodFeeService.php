<?php

namespace App\Services;

class PaymentMethodFeeService
{
    private const QRIS_FEE_PERCENTAGE = 0.7;

    private const BANK_TRANSFER_FEE_FLAT = 4000;

    /**
     * Calculate payment method fee.
     *
     * @param  string  $paymentMethod  'qris' or 'bank_transfer'
     * @param  int  $grossAmount  base amount in Rupiah (no decimals)
     * @return array{fee_amount: int, fee_percentage: float}
     *
     * @throws \InvalidArgumentException if payment method is unknown
     */
    public function calculate(string $paymentMethod, int $grossAmount): array
    {
        return match ($paymentMethod) {
            'qris' => [
                'fee_amount' => (int) ceil($grossAmount * self::QRIS_FEE_PERCENTAGE / 100),
                'fee_percentage' => self::QRIS_FEE_PERCENTAGE,
            ],
            'bank_transfer' => [
                'fee_amount' => self::BANK_TRANSFER_FEE_FLAT,
                'fee_percentage' => 0,
            ],
            default => throw new \InvalidArgumentException("Unknown payment method: {$paymentMethod}"),
        };
    }
}
