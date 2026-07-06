# Project Structure

**Last updated:** 2026-07-06

## Directory Tree

```
alinsyirah-backend/
├── app/
│   ├── Console/Commands/
│   │   └── GenerateMonthlyInvoices.php
│   ├── Exports/
│   │   ├── PaymentRecordExport.php
│   │   ├── StudentExport.php
│   │   └── TuitionInvoiceExport.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php
│   │   │   ├── PublicDocumentController.php
│   │   │   └── Api/
│   │   │       ├── AnnualPrepaymentController.php
│   │   │       ├── AuthController.php
│   │   │       ├── DocumentController.php
│   │   │       ├── ExportController.php
│   │   │       ├── ImportController.php
│   │   │       ├── MidtransWebhookController.php
│   │   │       ├── PaymentAttemptController.php
│   │   │       ├── StudentController.php
│   │   │       └── TuitionInvoiceController.php
│   │   ├── Middleware/
│   │   │   └── Cors.php
│   │   ├── Requests/
│   │   │   ├── AnnualPrepaymentRequest.php
│   │   │   ├── BundlePaymentRequest.php
│   │   │   ├── StoreStudentRequest.php
│   │   │   ├── StoreTuitionInvoiceRequest.php
│   │   │   ├── UpdateStudentRequest.php
│   │   │   └── Auth/
│   │   │       ├── LoginRequest.php
│   │   │       └── RegisterRequest.php
│   │   └── Resources/
│   │       ├── PaymentAttemptResource.php
│   │       ├── StudentResource.php
│   │       ├── TuitionInvoiceResource.php
│   │       └── UserResource.php
│   ├── Imports/
│   │   ├── StudentImport.php
│   │   ├── StudentTemplate.php
│   │   ├── TuitionInvoiceImport.php
│   │   └── TuitionInvoiceTemplate.php
│   ├── Models/
│   │   ├── PaymentAttempt.php
│   │   ├── PaymentAttemptInvoice.php
│   │   ├── PaymentNotification.php
│   │   ├── Student.php
│   │   ├── TuitionInvoice.php
│   │   └── User.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       ├── BundlePaymentService.php
│       ├── DocumentService.php
│       └── MidtransService.php
├── config/
├── database/
│   ├── factories/
│   │   ├── PaymentAttemptFactory.php
│   │   ├── StudentFactory.php
│   │   ├── TuitionInvoiceFactory.php
│   │   └── UserFactory.php
│   ├── migrations/ (10 migration files)
│   ├── seeders/
│   │   └── DatabaseSeeder.php
│   └── database.sqlite
├── resources/views/documents/
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   ├── Feature/ (10 test files)
│   └── Unit/ (2 test files)
└── .codebase/
```

## Key Files Per Directory

### app/Services/ - Business Logic
- MidtransService.php - Midtrans Payment Link API wrapper
- BundlePaymentService.php - Multi-invoice payment bundling with discount support
- DocumentService.php - PDF/HTML generation for bills and receipts via DomPDF

### app/Models/ - Domain Models
- Student.php - Student records (nis, name, school_class, parent info, monthly_fee)
- TuitionInvoice.php - Tuition invoices with state machine
- PaymentAttempt.php - Payment attempts linking to Midtrans
- PaymentAttemptInvoice.php - Pivot with allocated_amount
- PaymentNotification.php - Webhook notification log

### app/Http/Controllers/Api/ - API Endpoints (9 controllers)
- AuthController.php - Register, login, logout, me
- StudentController.php - Full CRUD (apiResource)
- TuitionInvoiceController.php - Index, store, show, pay
- PaymentAttemptController.php - Index, show, bundle, cancel
- AnnualPrepaymentController.php - Generate 12-month prepayment
- DocumentController.php - Bill/receipt HTML/PDF/download/share
- ImportController.php - Excel import preview/confirm/templates
- ExportController.php - Excel export by type
- MidtransWebhookController.php - Webhook receiver (CSRF exempt)

### app/Http/Resources/ - API Output Shaping
- StudentResource.php, TuitionInvoiceResource.php, PaymentAttemptResource.php, UserResource.php

## Naming Conventions

- Controllers: PascalCase, suffixed with Controller
- Models: PascalCase, singular
- Services: PascalCase, suffixed with Service
- Form Requests: PascalCase with HTTP verb prefix
- Resources: PascalCase, suffixed with Resource
- Factories: PascalCase, suffixed with Factory
- Migrations: snake_case with timestamp prefix
- Files: One class per file, PSR-4 autoloading
