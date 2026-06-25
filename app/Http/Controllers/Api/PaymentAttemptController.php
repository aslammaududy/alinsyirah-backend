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
            );

            return PaymentAttemptResource::make($attempt);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
