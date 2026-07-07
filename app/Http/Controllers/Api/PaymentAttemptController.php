<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BundlePaymentRequest;
use App\Http\Resources\PaymentAttemptResource;
use App\Models\PaymentAttempt;
use App\Models\TuitionInvoice;
use App\Services\BundlePaymentService;
use App\Services\MidtransService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentAttemptController extends Controller
{
    public function __construct(
        private readonly BundlePaymentService $bundleService,
        private readonly MidtransService $midtrans,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return PaymentAttemptResource::collection(
            PaymentAttempt::with('invoices')->paginate(20)
        );
    }

    public function show(PaymentAttempt $paymentAttempt): PaymentAttemptResource
    {
        return PaymentAttemptResource::make($paymentAttempt->load('invoices'));
    }

    /**
     * Create a bundled Payment Link covering multiple invoices.
     */
    public function bundle(BundlePaymentRequest $request): PaymentAttemptResource
    {
        $data = $request->validated();

        $invoices = TuitionInvoice::whereIn('id', $data['tuition_invoice_ids'])->get();

        if ($invoices->count() !== count($data['tuition_invoice_ids'])) {
            abort(422, 'One or more invoices not found.');
        }

        try {
            $attempt = $this->bundleService->bundle(
                invoices: $invoices,
                allocations: $data['allocations'],
                discountAmount: $data['discount_amount'] ?? 0,
                usageLimit: $data['usage_limit'] ?? null,
                expiry: $data['expiry'] ?? null,
                customerDetails: $data['customer_details'] ?? null,
                enabledPayments: $data['enabled_payments'] ?? null,
                callbacks: $data['callbacks'] ?? null,
                createdBy: $request->user()?->id,
                paymentMethod: $data['payment_method'],
                bank: $data['bank'] ?? null,
            );

            return PaymentAttemptResource::make($attempt);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    /**
     * Cancel a payment attempt and revert its linked invoices.
     *
     * - Paid attempts cannot be cancelled.
     * - annual_prepayment invoices are deleted permanently.
     * - manual/scheduled invoices are reverted to draft.
     */
    public function cancel(PaymentAttempt $paymentAttempt): PaymentAttemptResource
    {
        if ($paymentAttempt->status === 'paid') {
            abort(422, 'Cannot cancel a paid payment attempt.');
        }

        if ($paymentAttempt->isTerminal()) {
            abort(422, 'Payment attempt is already in a terminal state.');
        }

        DB::transaction(function () use ($paymentAttempt) {
            // Cancel the payment attempt.
            $paymentAttempt->update([
                'status' => 'cancelled',
                'payment_url' => null,
            ]);

            // Process linked invoices.
            foreach ($paymentAttempt->invoices as $invoice) {
                if ($invoice->generation_source === 'annual_prepayment') {
                    // Delete permanently to prevent invoice pile-up.
                    $invoice->delete();
                } else {
                    // Revert to draft so it can be paid later.
                    $invoice->transitionTo('draft');
                }
            }

            // Detach pivot records (after potential deletes).
            $paymentAttempt->paymentAttemptInvoices()->delete();
        });

        return PaymentAttemptResource::make($paymentAttempt->load('invoices'));
    }
}
