<?php

use App\Http\Controllers\Api\AnnualPrepaymentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\PaymentAttemptController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TuitionInvoiceController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::apiResource('students', StudentController::class);

    Route::get('tuition-invoices', [TuitionInvoiceController::class, 'index']);
    Route::post('tuition-invoices', [TuitionInvoiceController::class, 'store']);
    Route::get('tuition-invoices/{tuition_invoice}', [TuitionInvoiceController::class, 'show']);
    Route::post('tuition-invoices/{tuition_invoice}/pay', [TuitionInvoiceController::class, 'pay']);

    Route::get('payment-attempts', [PaymentAttemptController::class, 'index']);
    Route::get('payment-attempts/{payment_attempt}', [PaymentAttemptController::class, 'show']);
    Route::post('payment-attempts/bundle', [PaymentAttemptController::class, 'bundle']);
    Route::post('payment-attempts/{payment_attempt}/cancel', [PaymentAttemptController::class, 'cancel']);

    Route::post('annual-prepayments', [AnnualPrepaymentController::class, 'store']);

    // Bills & Receipts
    Route::get('invoices/{tuition_invoice}/bill', [DocumentController::class, 'bill']);
    Route::get('invoices/{tuition_invoice}/bill/download', [DocumentController::class, 'billDownload']);
    Route::get('payment-attempts/{payment_attempt}/receipt', [DocumentController::class, 'receipt']);
    Route::get('payment-attempts/{payment_attempt}/receipt/download', [DocumentController::class, 'receiptDownload']);
    Route::get('payment-attempts/{payment_attempt}/receipt/share', [DocumentController::class, 'receiptShareUrl']);

    // Excel Import
    Route::post('imports/preview', [ImportController::class, 'preview']);
    Route::post('imports/confirm', [ImportController::class, 'confirm']);
    Route::get('imports/template/students', [ImportController::class, 'studentTemplate']);
    Route::get('imports/template/tuition-invoices', [ImportController::class, 'invoiceTemplate']);

    // Excel Export
    Route::get('exports/{type}', [ExportController::class, 'export']);
});

Route::post('midtrans/webhook', MidtransWebhookController::class)->withoutMiddleware(VerifyCsrfToken::class);
