<?php

use App\Http\Controllers\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public signed receipt routes
Route::get('receipt/{payment_attempt}', [PublicDocumentController::class, 'receipt'])
    ->name('receipt.public')
    ->middleware('signed');

Route::get('receipt/{payment_attempt}/download', [PublicDocumentController::class, 'receiptDownload'])
    ->name('receipt.public.download')
    ->middleware('signed');
