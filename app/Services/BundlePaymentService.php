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

        return DB::transaction(function () use (
            $orderId, $grossAmount, $invoices, $discountAmount,
            $allocations, $student, $itemDetails, $usageLimit, $expiry,
            $customerDetails, $enabledPayments, $callbacks, $createdBy
        ) {
            $attempt = PaymentAttempt::create([
                'provider_order_id' => $orderId,
                'status' => 'creating',
                'discount_amount' => $discountAmount,
                'created_by' => $createdBy,
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
                $response = $this->midtrans->createPaymentLink([
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                    'usage_limit' => $usageLimit ?? 1,
                    'expiry' => $expiry,
                    'item_details' => $itemDetails,
                    'customer_details' => $customerDetails ?? [
                        'first_name' => $student->parent_name,
                        'email' => $student->parent_email,
                        'phone' => $student->parent_phone,
                    ],
                    'enabled_payments' => $enabledPayments,
                    'callbacks' => $callbacks,
                ]);
            } catch (RuntimeException $e) {
                $attempt->update(['status' => 'failed']);

                throw $e;
            }

            $updateData = [
                'payment_url' => $response['payment_url'],
                'status' => 'created',
                'provider_response' => [
                    'id' => $response['id'],
                    'order_id' => $response['order_id'],
                    'payment_url' => $response['payment_url'],
                    'expiry' => $response['expiry'] ?? null,
                ],
            ];

            if (isset($response['expiry'])) {
                $updateData['expiry_at'] = $response['expiry'];
            }

            if (isset($response['usage_limit'])) {
                $updateData['usage_limit'] = $response['usage_limit'];
            }

            $attempt->update($updateData);

            foreach ($invoices as $invoice) {
                $invoice->transitionTo('pending_payment');
            }

            $attempt->load('invoices');

            return $attempt;
        });
    }
}
