# Plan: Bills & Receipts + Excel Import/Export

## Requirements (Decisions D-01 through D-10)

| Decision | Choice |
|----------|--------|
| D-01 | Bill scope = Per individual Tuition Invoice (pending payment) |
| D-02 | Receipt scope = Per Payment Attempt (covers multiple invoices paid together) |
| D-03 | Excel Import = Students + Tuition Invoices. Export = Students + Tuition Invoices + Payment Records |
| D-04 | Bills & Receipts format = Both PDF (barryvdh/laravel-dompdf) and printable HTML (Blade view) |
| D-05 | Content = School name/logo, student info, parent info, invoice/payment details, Midtrans order ID |
| D-06 | Import = Validate → Preview → Confirm → Create, with downloadable .xlsx templates |
| D-07 | Export filters = Students: class, status. Invoices: period, fee_type, status, student. Payments: date_range, status, student. |
| D-08 | Bills admin-only. Receipts admin-only + public signed URL for parents. |
| D-09 | Receipt token = Laravel URL::temporarySignedRoute with 7-day expiry |
| D-10 | Numbering = Bills INV-{id}, Receipts RCP-{id} |

## Phase 1: Dependencies & Infrastructure
- Install `barryvdh/laravel-dompdf` and `maatwebsite/excel`
- Configure dompdf (remote enabled, font subsetting)
- Create `config/school.php` with name, address, phone, logo_path
- Complexity: Simple

## Phase 2: Bills & Receipts — Services & Blade Templates
- Create `app/Services/DocumentService.php` (PDF/HTML generation)
  - Generates bill numbers as `INV-{invoice_id}` for bills, `RCP-{payment_attempt_id}` for receipts
- Create `resources/views/documents/bill.blade.php`
- Create `resources/views/documents/receipt.blade.php`
- Create `resources/views/documents/partials/header.blade.php`
- Create `resources/views/documents/partials/footer.blade.php`
- Complexity: Standard

## Phase 3: Bills & Receipts — Controllers & Routes
- Create `app/Http/Controllers/Api/DocumentController.php` (authenticated routes)
- Create `app/Http/Controllers/PublicDocumentController.php` (public signed route)
- Add routes to `routes/api.php` and `routes/web.php`
  - Authenticated routes (bill, receipt, receipt/share) go in `routes/api.php` inside `auth:sanctum` group
  - Public routes (receipt view/download) go in `routes/web.php`
- Complexity: Standard

## Phase 4: Excel Import — Students & Invoices
- Create `app/Imports/StudentImport.php`
- Create `app/Imports/TuitionInvoiceImport.php`
- Create `app/Imports/StudentTemplate.php`
- Create `app/Imports/TuitionInvoiceTemplate.php`
- Create `app/Http/Controllers/Api/ImportController.php`
  - Preview returns: `{"token": "...", "rows": [...], "errors": [...], "summary": {"total": N, "valid": N, "invalid": N}}`
  - Confirm accepts `import_token` and returns: `{"created": N, "errors": [...]}`
- Add routes to `routes/api.php`
- Complexity: Standard

## Phase 5: Excel Export — Students, Invoices, Payments
- Create `app/Exports/StudentExport.php`
  - Filter query params: `?school_class=X-A`, `?status=active`
- Create `app/Exports/TuitionInvoiceExport.php`
  - Filter query params: `?period=2026-07`, `?fee_type=spp`, `?status=pending_payment`, `?student_id=5`
- Create `app/Exports/PaymentRecordExport.php`
  - Filter query params: `?date_from=2026-01-01`, `?date_to=2026-12-31`, `?status=paid`, `?student_id=5`
- Create `app/Http/Controllers/Api/ExportController.php`
- Add route to `routes/api.php`
- Complexity: Simple

## Phase 6: Testing
- Create test files: BillTest, ReceiptTest, ReceiptPublicAccessTest, ImportTest, ExportTest, DocumentServiceTest
- Each test class uses Pest PHP v4 syntax with `RefreshDatabase` trait
- Tests use existing model factories or create inline test data
- Verify: `php artisan test --compact --filter=TestName` passes
- Ensure StudentFactory and TuitionInvoiceFactory exist (check existing factories first)
- Complexity: Standard

## Phase 7: Documentation
- Add PHPDoc blocks to all new controllers
- Verify Scramble generates clean OpenAPI spec
- Complexity: Trivial

## New Routes Summary
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/invoices/{id}/bill | Sanctum | Bill HTML preview |
| GET | /api/invoices/{id}/bill/download | Sanctum | Bill PDF download |
| GET | /api/payment-attempts/{id}/receipt | Sanctum | Receipt HTML preview |
| GET | /api/payment-attempts/{id}/receipt/download | Sanctum | Receipt PDF download |
| GET | /api/payment-attempts/{id}/receipt/share | Sanctum | Get signed URL for parent |
| GET | /receipt/{id}?signature=... | Public | Parent receipt (signed URL) |
| GET | /receipt/{id}/download?signature=... | Public | Parent receipt PDF (signed URL) |
| POST | /api/imports/preview | Sanctum | Preview import data |
| POST | /api/imports/confirm | Sanctum | Confirm and create records |
| GET | /api/imports/template/students | Sanctum | Download student template |
| GET | /api/imports/template/tuition-invoices | Sanctum | Download invoice template |
| GET | /api/exports/{type} | Sanctum | Export data as xlsx |
