# Tech Stack

**Last updated:** 2026-07-06

## Framework & Runtime

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | ^8.3 | Required minimum |
| Laravel | ^13.8 | Primary framework |
| Node.js | (implied via Vite) | For frontend asset building |

## Key Packages

### Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^13.8 | Core Laravel framework |
| `laravel/sanctum` | ^4.3 | API token authentication (personal access tokens) |
| `laravel/tinker` | ^3.0 | REPL for debugging |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation (bills, receipts) |
| `dedoc/scramble` | ^0.13.29 | OpenAPI documentation at `/docs/api` |
| `maatwebsite/excel` | ^3.1 | Excel import/export (students, invoices, payments) |

### Development Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `pestphp/pest` | ^4.7 | Testing framework |
| `pestphp/pest-plugin-laravel` | ^4.1 | Laravel integration for Pest |
| `laravel/pint` | ^1.27 | Code formatting (PSR-12) |
| `laravel/pail` | ^1.2.5 | Real-time log viewer |
| `laravel/boost` | ^2.2 | Laravel Boost tooling |
| `laravel/pao` | ^1.0.6 | Development utility |
| `fakerphp/faker` | ^1.23 | Test data generation |
| `mockery/mockery` | ^1.6 | Mocking framework |
| `nunomaduro/collision` | ^8.6 | Error reporting |

## Frontend (Asset Building)

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^8.0.0 | Build tool |
| `laravel-vite-plugin` | ^3.1 | Laravel integration |
| `tailwindcss` | ^4.0.0 | CSS framework |
| `@tailwindcss/vite` | ^4.0.0 | Tailwind Vite plugin |

## Database

- **Type:** SQLite (for testing: `:memory:` via phpunit.xml)
- **Production:** SQLite (file-based: `database/database.sqlite`)
- **ORM:** Eloquent

## Authentication

- **System:** Laravel Sanctum (personal access tokens)
- **Token type:** Bearer token (`Authorization: Bearer <token>`)
- **SPA session auth:** Removed (`EnsureFrontendRequestsAreStateful` removed from API stack)
- **All API routes:** Protected via `auth:sanctum` middleware (except register, login, webhook)

## Testing

- **Framework:** Pest v4 (on top of PHPUnit)
- **Database:** SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- **Traits:** `LazilyRefreshDatabase` (per-test database refresh)
- **HTTP testing:** `$this->postJson()`, `$this->withToken()`
- **Mocking:** `Http::fake()` for external API calls

## PDF Generation

- **Package:** DomPDF (`barryvdh/laravel-dompdf`)
- **Usage:** Bill and receipt generation (HTML templates → PDF)
- **Views:** `resources/views/documents/bill.blade.php`, `resources/views/documents/receipt.blade.php`

## Excel Import/Export

- **Package:** Maatwebsite Excel v3
- **Import:** Students and tuition invoices via `.xlsx` files
- **Export:** Students, tuition invoices, payment records to `.xlsx`
- **Templates:** Downloadable import templates for students and invoices

## Payment Integration

- **Provider:** Midtrans (Payment Link API)
- **Auth:** HTTP Basic Auth with server key
- **Endpoint:** Configurable via `midtrans.payment_link_url`
- **Webhook:** POST `/api/midtrans/webhook` (CSRF exempt)
- **Status:** `MIDTRANS_SERVER_KEY` not set in `.env` — payment operations will fail without it

## Server Requirements

- PHP 8.3+ with extensions: openssl, pdo, mbstring, tokenizer, xml, ctype, json, bcmath
- SQLite3 extension
- Composer
- Node.js + npm (for asset building)
