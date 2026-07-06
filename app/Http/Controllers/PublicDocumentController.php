<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\DocumentService;
use Illuminate\Http\Response;

class PublicDocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
    ) {}

    public function receipt(PaymentAttempt $paymentAttempt): Response
    {
        abort_unless($paymentAttempt->status === 'paid', 404, 'Receipt not available.');

        $html = $this->documentService->generateReceiptHtml($paymentAttempt);

        return response($html)->header('Content-Type', 'text/html');
    }

    public function receiptDownload(PaymentAttempt $paymentAttempt): Response
    {
        abort_unless($paymentAttempt->status === 'paid', 404, 'Receipt not available.');

        return $this->documentService->generateReceiptPdf($paymentAttempt);
    }
}
