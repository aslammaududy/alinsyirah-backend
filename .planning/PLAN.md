# Plan: Switch from Midtrans Payment Link to Core API (QRIS & Bank Transfer)

## Overview

Replace the current Payment Link integration (`/v1/payment-links`) with Midtrans Core API (`/v2/charge`) to generate QRIS QR codes and Bank Transfer virtual account numbers directly.

## Requirements

- API consumers pass `payment_method` (`"qris"` or `"bank_transfer"`) on every charge
- QRIS response exposes a `qr_code_url`; Bank Transfer exposes `va_number` and `bank`
- Processor fees (QRIS 0.7%, Bank Transfer Rp4,000 flat) are stored and surfaced as separate line items
- Existing webhook handler works unchanged

## Implementation Steps

### Step 1 — Migration: Add payment_method and fee columns
**File**: `database/migrations/2026_07_07_000001_add_payment_method_to_payment_attempts.php`
**Changes**: Add `payment_method`, `fee_amount`, `fee_percentage` columns to `payment_attempts`

### Step 2 — Config: Add Core API base URL
**File**: `config/midtrans.php`
**Changes**: Add `core_api_base_url` key for sandbox/production

### Step 3 — Service: PaymentMethodFeeService
**File**: `app/Services/PaymentMethodFeeService.php`
**Changes**: Create fee calculation service (QRIS 0.7%, Bank Transfer Rp4,000)

### Step 4 — Form Requests: Validate payment_method
**Files**: `app/Http/Requests/BundlePaymentRequest.php`, `app/Http/Requests/AnnualPrepaymentRequest.php`
**Changes**: Add `payment_method` and `bank` validation

### Step 5 — Model: Update PaymentAttempt
**File**: `app/Models/PaymentAttempt.php`
**Changes**: Add new fields to fillable, add `getTotalAmount()` method

### Step 6 — MidtransService: Add Core API charge methods
**File**: `app/Services/MidtransService.php`
**Changes**: Add `createCharge()` method, update order ID prefix

### Step 7 — BundlePaymentService: Accept payment method
**File**: `app/Services/BundlePaymentService.php`
**Changes**: Accept payment method, calculate fees, call `createCharge()`

### Step 8 — Controllers: Update pay and bundle endpoints
**Files**: `app/Http/Controllers/Api/TuitionInvoiceController.php`, `app/Http/Controllers/Api/PaymentAttemptController.php`, `app/Http/Controllers/Api/AnnualPrepaymentController.php`
**Changes**: Accept and pass `payment_method` parameter

### Step 9 — Resource: Expose QR/VA data and fees
**File**: `app/Http/Resources/PaymentAttemptResource.php`
**Changes**: Add `qr_code_url`, `va_number`, `bank`, `fee_amount`, `total_amount`

### Step 10 — Factory: Update for new columns
**File**: `database/factories/PaymentAttemptFactory.php`
**Changes**: Add defaults for new columns

### Step 11-14 — Tests
**Files**: Various test files
**Changes**: Unit tests for fee service and charge method, update existing tests

### Step 15 — Cleanup: Remove Payment Link methods
**File**: `app/Services/MidtransService.php`
**Changes**: Remove old Payment Link methods after tests pass

## Success Criteria
- QRIS payments return `qr_code_url`
- Bank Transfer payments return `va_number` and `bank`
- Fees calculated correctly (QRIS 0.7%, Bank Transfer Rp4,000)
- All tests pass
