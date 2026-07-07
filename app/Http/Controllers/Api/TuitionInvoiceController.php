<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayTuitionInvoiceRequest;
use App\Http\Requests\StoreTuitionInvoiceRequest;
use App\Http\Resources\PaymentAttemptResource;
use App\Http\Resources\TuitionInvoiceResource;
use App\Models\PaymentAttempt;
use App\Models\PaymentAttemptInvoice;
use App\Models\TuitionInvoice;
use App\Services\MidtransService;
use App\Services\PaymentMethodFeeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TuitionInvoiceController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentMethodFeeService $feeService,
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
     * Create a Payment Charge for a single invoice (per-invoice flow).
     */
    public function pay(PayTuitionInvoiceRequest $request, TuitionInvoice $tuitionInvoice): PaymentAttemptResource
    {
        if ($tuitionInvoice->isTerminal()) {
            abort(422, 'Cannot create payment for a terminal invoice.');
        }

        if (! $tuitionInvoice->canTransitionTo('pending_payment')) {
            abort(422, "Invoice cannot be moved to pending_payment from its current state '{$tuitionInvoice->status}'.");
        }

        $orderId = $this->midtrans->generateOrderId();
        $grossAmount = $tuitionInvoice->amount;

        $validated = $request->validated();
        $paymentMethod = $validated['payment_method'];
        $bank = $validated['bank'] ?? null;

        $fee = $this->feeService->calculate($paymentMethod, $grossAmount);
        $totalAmount = $grossAmount + $fee['fee_amount'];

        $student = $tuitionInvoice->student;

        $itemDetails = [
            [
                'id' => (string) $tuitionInvoice->id,
                'name' => $tuitionInvoice->description ?? "{$tuitionInvoice->fee_type} - {$tuitionInvoice->period}",
                'price' => $grossAmount,
                'quantity' => 1,
            ],
        ];

        if ($fee['fee_amount'] > 0) {
            $itemDetails[] = [
                'id' => 'fee',
                'name' => $paymentMethod === 'qris' ? 'Biaya Admin QRIS (0.7%)' : 'Biaya Admin Transfer Bank',
                'price' => $fee['fee_amount'],
                'quantity' => 1,
            ];
        }

        try {
            $result = DB::transaction(function () use (
                $tuitionInvoice, $orderId, $totalAmount, $student,
                $itemDetails, $paymentMethod, $fee
            ) {
                $attempt = PaymentAttempt::create([
                    'provider_order_id' => $orderId,
                    'status' => 'creating',
                    'payment_method' => $paymentMethod,
                    'fee_amount' => $fee['fee_amount'],
                    'fee_percentage' => $fee['fee_percentage'],
                ]);

                PaymentAttemptInvoice::create([
                    'payment_attempt_id' => $attempt->id,
                    'tuition_invoice_id' => $tuitionInvoice->id,
                    'allocated_amount' => $tuitionInvoice->amount,
                ]);

                $chargeParams = [
                    'payment_type' => $paymentMethod,
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $totalAmount,
                    ],
                    'item_details' => $itemDetails,
                    'customer_details' => [
                        'first_name' => $student->parent_name,
                        'email' => $student->parent_email,
                        'phone' => $student->parent_phone,
                    ],
                ];

                if ($paymentMethod === 'bank_transfer') {
                    $chargeParams['bank_transfer'] = ['bank' => $bank ?? 'bsi'];
                }

                $response = $this->midtrans->createCharge($chargeParams);

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
