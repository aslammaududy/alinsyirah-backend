<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTuitionInvoiceRequest;
use App\Http\Resources\PaymentAttemptResource;
use App\Http\Resources\TuitionInvoiceResource;
use App\Models\PaymentAttempt;
use App\Models\PaymentAttemptInvoice;
use App\Models\TuitionInvoice;
use App\Services\MidtransService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TuitionInvoiceController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TuitionInvoiceResource::collection(
            TuitionInvoice::with('student')->paginate(20)
        );
    }

    public function store(StoreTuitionInvoiceRequest $request): TuitionInvoiceResource
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['generation_source'] ??= 'manual';

        $invoice = TuitionInvoice::create($data);

        return TuitionInvoiceResource::make($invoice->load('student'));
    }

    public function show(TuitionInvoice $tuitionInvoice): TuitionInvoiceResource
    {
        return TuitionInvoiceResource::make($tuitionInvoice->load('student'));
    }

    /**
     * Create a Payment Link for a single invoice (per-invoice flow).
     */
    public function pay(TuitionInvoice $tuitionInvoice): PaymentAttemptResource
    {
        if ($tuitionInvoice->isTerminal()) {
            abort(422, 'Cannot create payment for a terminal invoice.');
        }

        if (! $tuitionInvoice->canTransitionTo('pending_payment')) {
            abort(422, "Invoice cannot be moved to pending_payment from its current state '{$tuitionInvoice->status}'.");
        }

        $orderId = $this->midtrans->generateOrderId();
        $grossAmount = $tuitionInvoice->amount;

        $student = $tuitionInvoice->student;

        try {
            $result = DB::transaction(function () use ($tuitionInvoice, $orderId, $grossAmount, $student) {
                $attempt = PaymentAttempt::create([
                    'provider_order_id' => $orderId,
                    'status' => 'creating',
                ]);

                PaymentAttemptInvoice::create([
                    'payment_attempt_id' => $attempt->id,
                    'tuition_invoice_id' => $tuitionInvoice->id,
                    'allocated_amount' => $grossAmount,
                ]);

                $response = $this->midtrans->createPaymentLink([
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                    'item_details' => [
                        [
                            'id' => (string) $tuitionInvoice->id,
                            'name' => $tuitionInvoice->description ?? "{$tuitionInvoice->fee_type} - {$tuitionInvoice->period}",
                            'price' => $grossAmount,
                            'quantity' => 1,
                        ],
                    ],
                    'customer_details' => [
                        'first_name' => $student->parent_name,
                        'email' => $student->parent_email,
                        'phone' => $student->parent_phone,
                    ],
                ]);

                $attempt->update([
                    'payment_url' => $response['payment_url'],
                    'status' => 'created',
                    'provider_response' => [
                        'id' => $response['id'],
                        'order_id' => $response['order_id'],
                        'payment_url' => $response['payment_url'],
                        'expiry' => $response['expiry'] ?? null,
                    ],
                ]);

                if (isset($response['expiry'])) {
                    $attempt->update(['expiry_at' => $response['expiry']]);
                }

                $tuitionInvoice->transitionTo('pending_payment');

                $attempt->load('invoices');

                return $attempt;
            });

            return PaymentAttemptResource::make($result);
        } catch (RuntimeException $e) {
            PaymentAttempt::where('provider_order_id', $orderId)
                ->where('status', 'creating')
                ->update(['status' => 'failed']);

            throw $e;
        }
    }
}
