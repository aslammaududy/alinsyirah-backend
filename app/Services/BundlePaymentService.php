<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\PaymentAttemptInvoice;
use App\Models\TuitionInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BundlePaymentService
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentMethodFeeService $feeService,
    ) {}

    public function bundle(
        Collection $invoices,
        array $allocations,
        int $discountAmount = 0,
        ?int $usageLimit = null,
        ?array $expiry = null,
        ?array $customerDetails = null,
        ?array $enabledPayments = null,
        ?array $callbacks = null,
        ?int $createdBy = null,
        string $paymentMethod = 'qris',
        ?string $bank = null,
    ): PaymentAttempt {
        $terminal = $invoices->first(fn (TuitionInvoice $inv) => $inv->isTerminal());
        if ($terminal) {
            throw new RuntimeException("Invoice {$terminal->id} is already in a terminal state ({$terminal->status}).");
        }

        $nonTransitionable = $invoices->first(
            fn (TuitionInvoice $inv) => ! $inv->canTransitionTo('pending_payment')
        );
        if ($nonTransitionable) {
            throw new RuntimeException("Invoice {$nonTransitionable->id} cannot be moved to pending_payment from its current state '{$nonTransitionable->status}'.");
        }

        $alreadyLinkedIds = PaymentAttemptInvoice::whereIn('tuition_invoice_id', $invoices->pluck('id'))
            ->whereHas('paymentAttempt', fn ($q) => $q->whereNotIn('status', ['paid', 'expired', 'cancelled']))
            ->pluck('tuition_invoice_id');

        if ($alreadyLinkedIds->isNotEmpty()) {
            throw new RuntimeException('Invoices already attached to a non-terminal payment attempt: '.$alreadyLinkedIds->implode(', '));
        }

        $totalAllocated = (int) collect($allocations)->sum('allocated_amount');
        $grossAmount = $totalAllocated - $discountAmount;

        if ($grossAmount <= 0) {
            throw new RuntimeException('Gross amount must be greater than zero after discount.');
        }

        $fee = $this->feeService->calculate($paymentMethod, $grossAmount);
        $totalAmount = $grossAmount + $fee['fee_amount'];

        $orderId = $this->midtrans->generateOrderId();

        $student = $invoices->first()->student;

        $itemDetails = $invoices->map(function (TuitionInvoice $inv) use ($allocations) {
            $allocation = collect($allocations)->firstWhere('id', $inv->id);

            return [
                'id' => (string) $inv->id,
                'name' => $inv->description ?? "{$inv->fee_type} - {$inv->period}",
                'price' => $allocation['allocated_amount'] ?? $inv->amount,
                'quantity' => 1,
            ];
        })->values()->toArray();

        if ($fee['fee_amount'] > 0) {
            $itemDetails[] = [
                'id' => 'fee',
                'name' => $paymentMethod === 'qris' ? 'Biaya Admin QRIS (0.7%)' : 'Biaya Admin Transfer Bank',
                'price' => $fee['fee_amount'],
                'quantity' => 1,
            ];
        }

        return DB::transaction(function () use (
            $orderId, $totalAmount, $invoices, $discountAmount,
            $allocations, $student, $itemDetails,
            $customerDetails, $createdBy,
            $paymentMethod, $fee
        ) {
            $attempt = PaymentAttempt::create([
                'provider_order_id' => $orderId,
                'status' => 'creating',
                'discount_amount' => $discountAmount,
                'created_by' => $createdBy,
                'payment_method' => $paymentMethod,
                'fee_amount' => $fee['fee_amount'],
                'fee_percentage' => $fee['fee_percentage'],
            ]);

            foreach ($invoices as $invoice) {
                $allocation = collect($allocations)->firstWhere('id', $invoice->id);

                PaymentAttemptInvoice::create([
                    'payment_attempt_id' => $attempt->id,
                    'tuition_invoice_id' => $invoice->id,
                    'allocated_amount' => $allocation['allocated_amount'] ?? $invoice->amount,
                ]);
            }

            // TODO: Apply discount logic — business rule for how discount_amount is
            // determined (e.g. percentage off annual prepayment) is not yet decided.
            // The structural support (discount_amount on payment_attempts,
            // gross_amount = sum(allocated) - discount) is in place.

            try {
                $chargeParams = [
                    'payment_type' => $paymentMethod,
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $totalAmount,
                    ],
                    'item_details' => $itemDetails,
                    'customer_details' => $customerDetails ?? [
                        'first_name' => $student->parent_name,
                        'email' => $student->parent_email,
                        'phone' => $student->parent_phone,
                    ],
                ];

                if ($paymentMethod === 'bank_transfer') {
                    $chargeParams['bank_transfer'] = ['bank' => $bank ?? 'bsi'];
                }

                $response = $this->midtrans->createCharge($chargeParams);
            } catch (RuntimeException $e) {
                $attempt->update(['status' => 'failed']);

                throw $e;
            }

            $providerResponse = [
                'status_code' => $response['status_code'] ?? null,
                'transaction_status' => $response['transaction_status'] ?? null,
            ];

            if ($paymentMethod === 'qris' && isset($response['actions'][0]['url'])) {
                $providerResponse['qr_code_url'] = $response['actions'][0]['url'];
            }

            if ($paymentMethod === 'bank_transfer' && isset($response['va_numbers'][0])) {
                $providerResponse['va_number'] = $response['va_numbers'][0]['va_number'];
                $providerResponse['bank'] = $response['va_numbers'][0]['bank'];
            }

            $attempt->update([
                'status' => 'created',
                'provider_response' => $providerResponse,
            ]);

            foreach ($invoices as $invoice) {
                $invoice->transitionTo('pending_payment');
            }

            $attempt->load('invoices');

            return $attempt;
        });
    }
}
