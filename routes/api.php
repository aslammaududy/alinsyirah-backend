<?php

use App\Http\Controllers\Api\AnnualPrepaymentController;
use App\Http\Controllers\Api\AuthController;
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

    Route::post('annual-prepayments', [AnnualPrepaymentController::class, 'store']);
});

Route::post('midtrans/webhook', MidtransWebhookController::class)->withoutMiddleware(VerifyCsrfToken::class);
