<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\TuitionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DocumentService
{
    public function generateBillPdf(TuitionInvoice $invoice): Response
    {
        $data = $this->getBillData($invoice);
        $pdf = Pdf::loadView('documents.bill', $data);
        $filename = 'tagihan-'.$this->getBillNumber($invoice).'.pdf';

        return $pdf->download($filename);
    }

    public function generateBillHtml(TuitionInvoice $invoice): string
    {
        $data = $this->getBillData($invoice);

        return view('documents.bill', $data)->render();
    }

    public function generateReceiptPdf(PaymentAttempt $attempt): Response
    {
        $data = $this->getReceiptData($attempt);
        $pdf = Pdf::loadView('documents.receipt', $data);
        $filename = 'bukti-pembayaran-'.$this->getReceiptNumber($attempt).'.pdf';

        return $pdf->download($filename);
    }

    public function generateReceiptHtml(PaymentAttempt $attempt): string
    {
        $data = $this->getReceiptData($attempt);

        return view('documents.receipt', $data)->render();
    }

    public function getBillNumber(TuitionInvoice $invoice): string
    {
        return 'INV-'.$invoice->id;
    }

    public function getReceiptNumber(PaymentAttempt $attempt): string
    {
        return 'RCP-'.$attempt->id;
    }

    protected function getBillData(TuitionInvoice $invoice): array
    {
        $invoice->load('student');

        return [
            'invoice' => $invoice,
            'student' => $invoice->student,
            'billNumber' => $this->getBillNumber($invoice),
            'school' => [
                'name' => config('school.name', 'Al Insyirah'),
                'address' => config('school.address', ''),
                'phone' => config('school.phone', ''),
                'logo' => $this->getLogoBase64(),
            ],
        ];
    }

    protected function getReceiptData(PaymentAttempt $attempt): array
    {
        $attempt->load(['invoices.student', 'invoices']);

        $student = $attempt->invoices->first()?->student;

        return [
            'attempt' => $attempt,
            'student' => $student,
            'invoices' => $attempt->invoices,
            'receiptNumber' => $this->getReceiptNumber($attempt),
            'totalAmount' => $attempt->invoices->sum('pivot.allocated_amount'),
            'school' => [
                'name' => config('school.name', 'Al Insyirah'),
                'address' => config('school.address', ''),
                'phone' => config('school.phone', ''),
                'logo' => $this->getLogoBase64(),
            ],
        ];
    }

    protected function getLogoBase64(): ?string
    {
        $path = config('school.logo_path');

        if (! $path || ! file_exists($path)) {
            return null;
        }

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $base64 = base64_encode($data);

        return 'data:image/'.$type.';base64,'.$base64;
    }
}
