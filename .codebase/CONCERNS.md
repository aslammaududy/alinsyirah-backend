# Known Issues & Concerns

**Last updated:** 2026-07-06

## Critical Issues

### 1. MIDTRANS_SERVER_KEY Not Set
- **Impact:** All payment operations (pay, bundle, annual-prepayments) will throw RuntimeException
- **Location:** `.env.example` missing `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`
- **Workaround:** Set `MIDTRANS_SERVER_KEY` and `MIDTRANS_PAYMENT_LINK_URL` in `.env`
- **Config keys needed:**
  - `midtrans.server_key`
  - `midtrans.payment_link_url`
- **Status:** Known issue, documented in AGENTS.md
- **CORS:** Handled by Sanctum — no separate middleware configuration needed. The placeholder `Cors.php` middleware is unused and can be removed.


## TODO Items in Code

### BundlePaymentService (app/Services/BundlePaymentService.php, line 93)
```
// TODO: Apply discount logic — business rule for how discount_amount is
// determined (e.g. percentage off annual prepayment) is not yet decided.
// The structural support (discount_amount on payment_attempts,
// gross_amount = sum(allocated) - discount) is in place.
```
- **Impact:** Discount amounts are accepted but not calculated automatically
- **Status:** Structural support exists; business rules pending

## Technical Debt

### 1. No Rate Limiting
- API endpoints have no rate limiting configured
- Auth endpoints (register, login) are vulnerable to brute force
- Midtrans webhook has no rate limiting

### 2. No API Versioning
- All routes in `/api/` without version prefix (e.g., `/api/v1/`)
- Breaking changes will affect all consumers

### 3. No Comprehensive Error Handling
- Controllers use `abort()` for error responses (422, 404)
- No structured error response format (e.g., `{ "error": {...} }`)
- Midtrans errors bubble up as RuntimeException

### 4. Import Preview Cache Cleanup
- Import preview data stored in Cache with 30-minute TTL
- No scheduled job to clean up orphaned files in `storage/app/imports/`
- `@unlink($filePath)` in confirm handler may fail silently

### 5. Document Service Logo Handling
- `getLogoBase64()` reads file from disk on every request
- No caching of base64-encoded logo
- Performance impact on high-traffic scenarios

## Security Considerations

### 1. Webhook Signature Verification
- Signature verification is implemented correctly (SHA-512)
- Invalid signatures are logged to `payment_notifications` table
- But invalid webhook payloads still return 400 (not 401/403)

### 2. Signed URL Expiry
- Receipt share URLs expire after 7 days
- No way to revoke a signed URL before expiry
- Consider shorter expiry for production

### 3. Payment Amount Validation
- `allocated_amount` in pivot table is not validated against invoice amount
- Potential for over-allocation or under-allocation
- No server-side validation in BundlePaymentService

### 4. No Authorization Policies
- All authenticated users have full access to all resources
- No role-based access control (admin vs staff vs viewer)
- `StudentController::destroy()` allows any authenticated user to delete students

## Scaling Concerns

### 1. SQLite Limitations
- SQLite does not support concurrent writes well
- Suitable for development/testing but should migrate to PostgreSQL/MySQL for production
- No connection pooling

### 2. PDF Generation Performance
- DomPDF generates PDF synchronously in the request
- Large receipts (many invoices) may timeout
- Consider queue-based PDF generation for large documents

### 3. Excel Import/Export
- Maatwebsite Excel processes files in-memory
- Large files (>10k rows) may cause memory exhaustion
- No chunked processing configured

### 4. No Queue Configuration
- Queue set to `sync` (no background processing)
- PDF generation, email sending (if added) would block requests

## Missing Features

### 1. No Email Notifications
- No welcome email on registration
- No payment confirmation email
- No invoice reminder emails

### 2. No Webhook Retry Logic
- If webhook processing fails, Midtrans may retry but system has no idempotency key beyond order_id
- No dead letter queue for failed webhooks

### 3. No Audit Trail
- Changes to students, invoices, payments are not logged
- No soft deletes on any model
- `payment_notifications` only stores webhook payloads

### 4. No Scheduled Invoice Expiry
- Invoices with status `pending_payment` never auto-expire
- `expired` status only set via webhook (Midtrans-side expiry)
- No Artisan command to mark overdue invoices as expired

## Environment Configuration

Missing from `.env.example`:
- `MIDTRANS_SERVER_KEY`
- `MIDTRANS_CLIENT_KEY`
- `MIDTRANS_PAYMENT_LINK_URL`
- `SCHOOL_NAME`
- `SCHOOL_ADDRESS`
- `SCHOOL_PHONE`
- `SCHOOL_LOGO_PATH`
