<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\PaymentNotification;
use App\Models\TuitionInvoice;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
    ) {}

    /**
     * Handle incoming Midtrans Payment Link HTTP notification (webhook).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '0';
        $signatureKey = $payload['signature_key'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? '';

        $signatureValid = $this->midtrans->verifySignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $signatureKey
        );

        PaymentNotification::create([
            'provider_order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'signature_valid' => $signatureValid,
            'raw_payload' => $payload,
        ]);

        if (! $signatureValid) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $attempt = PaymentAttempt::where('provider_order_id', $orderId)->first();

        if (! $attempt) {
            return response()->json(['message' => 'Payment attempt not found'], 404);
        }

        if ($attempt->isTerminal()) {
            // Idempotent: terminal state is never overwritten.
            return response()->json(['message' => 'Payment attempt already in terminal state']);
        }

        if (! $attempt->canTransitionTo($this->mapMidtransStatus($transactionStatus, $fraudStatus))) {
            return response()->json(['message' => 'No state transition needed']);
        }

        DB::transaction(function () use ($attempt, $transactionStatus, $fraudStatus) {
            $newStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

            if ($newStatus === null) {
                return;
            }

            if ($newStatus === 'paid') {
                $invoices = TuitionInvoice::whereIn(
                    'id',
                    $attempt->paymentAttemptInvoices()->pluck('tuition_invoice_id')
                )->get();

                foreach ($invoices as $invoice) {
                    if (! $invoice->isTerminal()) {
                        $invoice->transitionTo('paid');
                    }
                }
            }

            $attempt->transitionTo($newStatus);
        });

        return response()->json(['message' => 'OK']);
    }

    private function mapMidtransStatus(string $transactionStatus, string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'accept' ? 'paid' : null,
            'settlement' => 'paid',
            'pending' => 'pending',
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'deny' => 'failed',
            default => null,
        };
    }
}
