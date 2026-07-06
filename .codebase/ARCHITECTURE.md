# Architecture

**Last updated:** 2026-07-06

## System Overview

SIMDIK Al Insyirah is a tuition payment management backend for Al Insyirah school. It handles student registration, tuition invoice generation, payment processing via Midtrans, and document generation (bills/receipts).

## Component Diagram

```
API Clients (Admin Dashboard, Frontend)
       |
       | HTTP/JSON
       v
+------------------------------------------+
|           Laravel Application            |
+------------------------------------------+
|  API Layer (routes/api.php)              |
|    Auth, Students, Invoices, Payments,   |
|    Documents, Import/Export              |
|         |                                |
|  Controllers (app/Http/Controllers/Api/) |
|         |                                |
|  Services (app/Services/)                |
|    MidtransService - Payment Link API    |
|    BundlePaymentService - Multi-invoice  |
|    DocumentService - PDF/HTML bills      |
|         |                                |
|  Models (app/Models/)                    |
|    User, Student, TuitionInvoice,        |
|    PaymentAttempt, PaymentAttemptInvoice |
|         |                                |
|  SQLite Database                         |
+------------------------------------------+
       |
       v
Midtrans Payment Link API (external)
```

## Data Flow

### 1. Single Invoice Payment
Admin -> POST /api/tuition-invoices/{id}/pay -> Creates PaymentAttempt + Pivot -> MidtransService::createPaymentLink() -> Invoice to pending_payment -> User pays -> Webhook -> Invoice to paid

### 2. Bundle Payment (Multiple Invoices)
Admin -> POST /api/payment-attempts/bundle -> BundlePaymentService::bundle() -> Creates PaymentAttempt + Pivots -> MidtransService::createPaymentLink() -> All invoices to pending_payment

### 3. Annual Prepayment
Admin -> POST /api/annual-prepayments -> Generates 12 monthly SPP invoices (source: annual_prepayment) -> BundlePaymentService::bundle() -> Payment Link created

### 4. Payment Cancellation
Admin -> POST /api/payment-attempts/{id}/cancel -> Deactivates Midtrans link (best-effort) -> annual_prepayment invoices deleted, manual invoices to draft -> PaymentAttempt to cancelled

### 5. Document Generation
- GET /api/invoices/{id}/bill -> DocumentService::generateBillHtml() -> HTML
- GET /api/invoices/{id}/bill/download -> DocumentService::generateBillPdf() -> PDF
- GET /api/payment-attempts/{id}/receipt/share -> Temporary signed URL (7 days)

## Status Machines

### TuitionInvoice
- draft -> pending_payment -> paid (terminal)
- draft -> cancelled (terminal)
- pending_payment -> expired -> pending_payment (retry allowed)
- paid, cancelled: terminal states, never overwritten

### PaymentAttempt
- creating -> created -> paid (terminal)
- creating -> failed (terminal)
- created -> expired (terminal)
- created -> cancelled (terminal)

## Key Design Patterns

1. State Machines: canTransitionTo() / transitionTo() on TuitionInvoice and PaymentAttempt
2. Terminal State Protection: Idempotent webhook handling - terminal states never overwritten
3. Service Layer: MidtransService, BundlePaymentService, DocumentService
4. Pivot Model: PaymentAttemptInvoice extends Pivot with allocated_amount
5. Form Requests: StoreStudentRequest, BundlePaymentRequest, etc.
6. API Resources: StudentResource, TuitionInvoiceResource, PaymentAttemptResource
7. Temporary Signed URLs: URL::temporarySignedRoute() for secure public receipt access
8. Webhook CSRF Exempt: /midtrans/webhook without VerifyCsrfToken middleware
