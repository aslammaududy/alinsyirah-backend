<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\TuitionInvoice;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
    ) {}

    public function bill(TuitionInvoice $tuitionInvoice): Response
    {
        abort_unless($tuitionInvoice->status === 'pending_payment', 404, 'Bill only available for pending invoices.');

        $html = $this->documentService->generateBillHtml($tuitionInvoice);

        return response($html)->header('Content-Type', 'text/html');
    }

    public function billDownload(TuitionInvoice $tuitionInvoice): Response
    {
        abort_unless($tuitionInvoice->status === 'pending_payment', 404, 'Bill only available for pending invoices.');

        return $this->documentService->generateBillPdf($tuitionInvoice);
    }

    public function receipt(PaymentAttempt $paymentAttempt): Response
    {
        abort_unless($paymentAttempt->status === 'paid', 404, 'Receipt only available for paid attempts.');

        $html = $this->documentService->generateReceiptHtml($paymentAttempt);

        return response($html)->header('Content-Type', 'text/html');
    }

    public function receiptDownload(PaymentAttempt $paymentAttempt): Response
    {
        abort_unless($paymentAttempt->status === 'paid', 404, 'Receipt only available for paid attempts.');

        return $this->documentService->generateReceiptPdf($paymentAttempt);
    }

    public function receiptShareUrl(PaymentAttempt $paymentAttempt): JsonResponse
    {
        abort_unless($paymentAttempt->status === 'paid', 404, 'Receipt only available for paid attempts.');

        $url = URL::temporarySignedRoute(
            'receipt.public',
            now()->addDays(7),
            ['payment_attempt' => $paymentAttempt->id],
        );

        $downloadUrl = URL::temporarySignedRoute(
            'receipt.public.download',
            now()->addDays(7),
            ['payment_attempt' => $paymentAttempt->id],
        );

        return response()->json([
            'url' => $url,
            'download_url' => $downloadUrl,
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);
    }
}
