# SIMDIK Al Insyirah — Architecture diagrams

Visual reference for the school tuition payment backend (Laravel + Midtrans). All diagrams use [Mermaid](https://mermaid.js.org/), which renders natively on GitHub, GitLab, Notion, and most markdown viewers and editors (VS Code with the Mermaid extension, Obsidian, etc.).

---

## 1. Tuition payment flow (overview)

End-to-end path from invoice creation to confirmed payment. Covers both single-invoice and bundle (multi-invoice / annual prepayment) paths. Both converge at the same `BundlePaymentService` for Midtrans charge creation.

```mermaid
flowchart TD
    A["Invoices exist in draft"] --> B{"Staff action?"}

    B -->|"Single invoice pay"| C["POST /tuition-invoices/{id}/pay"]
    B -->|"Select multiple invoices"| D["POST /payment-attempts/bundle"]
    B -->|"Annual prepayment"| E["POST /annual-prepayments"]

    C --> F["TuitionInvoiceController.pay<br/>Creates 1 PaymentAttempt + 1 pivot row"]
    D --> G["PaymentAttemptController.bundle<br/>Passes allocations to BundlePaymentService"]
    E --> H["AnnualPrepaymentController.store<br/>firstOrCreate 12 monthly SPP invoices<br/>then calls BundlePaymentService.bundle"]

    F --> I["BundlePaymentService.bundle<br/>Validates → calculates fee → DB transaction"]
    G --> I
    H --> I

    I --> J{"Midtrans createCharge"}
    J -->|"QRIS"| K["qr_code_url returned"]
    J -->|"Bank Transfer"| L["va_number returned"]
    J -->|"Error"| M["PaymentAttempt → failed"]

    K --> N["Parent scans QR / transfers"]
    L --> N

    N --> O["Midtrans webhook → POST /midtrans/webhook"]
    O --> P{"Signature valid?"}
    P -->|No| Q["Reject — 400"]
    P -->|Yes| R["Idempotency check<br/>Terminal states never overwritten"]
    R --> S["Map Midtrans status → internal status"]
    S -->|"capture+accept / settlement"| T["paid → transition all linked invoices"]
    S -->|"pending / expire / cancel / deny"| U["Update PaymentAttempt status"]
```

## 2. Database schema

Core tables and relationships. The many-to-many link between invoices and payment attempts is what powers bundled payments (multiple months, or a full annual prepayment, with QRIS or BSI Bank Transfer).

```mermaid
erDiagram
    USERS ||--o{ TUITION_INVOICES : creates
    USERS ||--o{ PAYMENT_ATTEMPTS : creates
    STUDENTS ||--o{ TUITION_INVOICES : has
    TUITION_INVOICES ||--o{ PAYMENT_ATTEMPT_INVOICES : included_in
    PAYMENT_ATTEMPTS ||--o{ PAYMENT_ATTEMPT_INVOICES : bundles
    PAYMENT_ATTEMPTS ||--o{ PAYMENT_NOTIFICATIONS : confirmed_by

    USERS {
        string name
        string email
    }
    STUDENTS {
        string nis
        string name
        string school_class
        int monthly_fee
        string status
    }
    TUITION_INVOICES {
        string period
        string fee_type
        int amount
        date due_date
        string status
        string generation_source
    }
    PAYMENT_ATTEMPTS {
        string provider_order_id
        string payment_method
        int fee_amount
        decimal fee_percentage
        string status
        int discount_amount
        text provider_response
    }
    PAYMENT_ATTEMPT_INVOICES {
        int allocated_amount
    }
    PAYMENT_NOTIFICATIONS {
        string transaction_status
        boolean signature_valid
        json raw_payload
    }
```

## 3. API surface structure

What's reachable without a token versus what sits behind Sanctum auth.

```mermaid
flowchart LR
    subgraph Public["Public access — no auth token"]
        direction TB
        P1["Register & login<br/>POST /auth/register, /auth/login"]
        P2["Midtrans webhook<br/>POST /midtrans/webhook"]
        P3["Public receipt view<br/>Signed URL (7-day expiry)"]
    end

    subgraph Auth["Authenticated — Sanctum bearer token"]
        direction TB
        A1["Students CRUD<br/>/api/students + photo upload"]
        A2["Invoices<br/>/api/tuition-invoices + /pay"]
        A3["Payments<br/>/api/payment-attempts + /bundle + /cancel"]
        A4["Prepayments<br/>POST /api/annual-prepayments"]
        A5["Documents<br/>Bills + Receipts + share URL"]
        A6["Excel Import<br/>Preview + Confirm + Templates"]
        A7["Excel Export<br/>Students, Invoices, Payments"]
    end
```

## 4. Frontend integration sequence

The call sequence a frontend client needs to follow, including the part that trips people up: the Midtrans webhook talks to the backend directly (same as before, works for both QRIS and Bank Transfer), never to the frontend, so the frontend has to poll for the final status.

```mermaid
sequenceDiagram
    participant FE as Frontend
    participant API as Backend API
    participant MT as Midtrans

    FE->>API: POST /auth/login
    API-->>FE: user + token
    FE->>API: GET /tuition-invoices (Bearer token)
    API-->>FE: list of invoices
    FE->>API: POST /payment-attempts/bundle<br/>{payment_method: "qris" or "bank_transfer"}
    API->>MT: Create charge (Core API)
    MT-->>API: QR code URL or VA number
    API-->>FE: qr_code_url or va_number + fee_amount
    Note right of FE: Display QR code or VA number to parent
    FE->>FE: Parent scans QR or transfers money
    MT->>API: Webhook (server-to-server, signed)
    Note right of API: Frontend never sees this call
    FE->>API: GET /payment-attempts/{id} (poll)
    API-->>FE: status: paid
```

---

## 5. Annual prepayment flow

Staff generates all 12 monthly SPP invoices for a student's year in one action, then bundles them into a single Midtrans Payment Link. Invoices are created with `firstOrCreate` so re-submissions are idempotent.

```mermaid
sequenceDiagram
    participant Staff
    participant API as Backend API
    participant DB as Database
    participant MT as Midtrans

    Staff->>API: POST /annual-prepayments<br/>{student_id, year, payment_method}

    loop month = 1 to 12
        API->>DB: TuitionInvoice::firstOrCreate<br/>(student_id, period: "YYYY-MM", fee_type: "spp")
        DB-->>API: invoice (existing or new, status: draft)
    end

    API->>API: Collect only new/draft invoices
    alt No eligible invoices
        API-->>Staff: 422 "All 12 invoices already exist in non-draft state"
    else Has eligible invoices
        API->>API: BundlePaymentService.bundle()<br/>Validate → calculate fee → create PaymentAttempt + pivots
        API->>MT: Midtrans createCharge
        MT-->>API: qr_code_url or va_number
        API-->>Staff: 201 PaymentAttemptResource<br/>{qr_code_url or va_number, linked_invoices: 12}
    end
```

## 6. Bundle payment flow (multi-invoice)

Staff selects any mix of invoices across students/periods and pays them with a single QR code or VA number. Each invoice gets a separate `allocated_amount` in the pivot table.

```mermaid
sequenceDiagram
    participant Staff
    participant API as Backend API
    participant DB as Database
    participant MT as Midtrans

    Staff->>API: POST /payment-attempts/bundle<br/>{tuition_invoice_ids: [...], allocations: [...],<br/>payment_method, discount_amount?}

    API->>DB: Find all invoices by IDs
    DB-->>API: invoices collection

    alt Count mismatch
        API-->>Staff: 422 "One or more invoices not found"
    else All found
        API->>API: BundlePaymentService.bundle()<br/>1. Check no invoice in terminal state<br/>2. Check all can transition to pending_payment<br/>3. Check no invoice linked to active attempt<br/>4. Calculate gross = sum(allocations) - discount<br/>5. Calculate fee via PaymentMethodFeeService
        API->>DB: BEGIN TRANSACTION<br/>Create PaymentAttempt (status: creating)<br/>Create PaymentAttemptInvoice rows
        API->>MT: Midtrans createCharge
        MT-->>API: QRIS → actions[0].url / Bank Transfer → va_numbers[0]
        API->>DB: Update PaymentAttempt → status: created<br/>Transition all invoices → pending_payment
        API-->>Staff: 201 PaymentAttemptResource<br/>{qr_code_url or va_number, fee_amount, invoices}
    end
```

## 7. Payment cancellation flow

Staff cancels a non-paid PaymentAttempt. The handling differs by invoice origin: annual prepayment invoices are deleted permanently to prevent pile-up; manual/scheduled invoices revert to draft so they can be paid later.

```mermaid
flowchart TD
    A["POST /payment-attempts/{id}/cancel"] --> B{"Attempt status?"}
    B -->|"paid"| C["422 — Cannot cancel a paid attempt"]
    B -->|"expired / cancelled (terminal)"| D["422 — Already in terminal state"]
    B -->|"creating / created / pending"| E["BEGIN DB TRANSACTION"]
    E --> F["Set PaymentAttempt status → cancelled<br/>Clear payment_url"]
    F --> G{"For each linked invoice"}
    G --> H{"generation_source?"}
    H -->|"annual_prepayment"| I["Delete invoice permanently"]
    H -->|"manual / scheduled"| J["Transition invoice → draft"]
    I --> K["Detach all pivot rows<br/>payment_attempt_invoices()->delete()"]
    J --> K
    K --> L["Return updated PaymentAttempt"]
```

## 8. Student management flow

Full CRUD with photo upload to cloud storage. Photo endpoints are throttled to prevent abuse.

```mermaid
flowchart TD
    subgraph CRUD["Student CRUD"]
        A["GET /students<br/>Paginated list (20/page)"]
        B["POST /students<br/>Create new student"]
        C["GET /students/{id}<br/>Show single student"]
        D["PUT /students/{id}<br/>Update student"]
        E["DELETE /students/{id}<br/>Delete student"]
    end

    subgraph Photos["Photo management"]
        F["POST /students/{id}/photo<br/>Upload to cloud storage<br/>throttle:upload middleware"]
        G["DELETE /students/{id}/photo<br/>Delete from cloud storage<br/>throttle:upload middleware"]
    end

    B --> H["Validates via StoreStudentRequest"]
    D --> I["Validates via UpdateStudentRequest"]
    F --> J["Student.uploadPhoto()<br/>Stores file, updates photo_path on model"]
    G --> K["Student.deletePhoto()<br/>Removes from cloud, clears photo_path"]
```

## 9. Excel import flow (two-step)

A preview-first import pattern prevents accidental data corruption. Step 1 parses the file without writing to the database. Step 2 uses a cached token to run the actual import.

```mermaid
sequenceDiagram
    participant Staff
    participant API as Backend API
    participant Cache as Laravel Cache
    participant FS as Filesystem
    participant DB as Database

    rect rgb(240, 248, 255)
        Note over Staff,DB: Step 1 — Preview (no DB writes)
        Staff->>API: POST /imports/preview<br/>{file: .xlsx, type: "students"|"tuition-invoices"}
        API->>API: Validate file (mimes:xlsx,xls, max:10MB)
        API->>FS: Store file at imports/{uuid}.xlsx
        API->>API: Parse with Maatwebsite Excel (preview mode)
        API->>Cache: Store preview data + file_path<br/>Key: import_{uuid} — TTL: 30 min
        API-->>Staff: {token: uuid, rows: [...], summary: {total: N}}
    end

    rect rgb(255, 248, 240)
        Note over Staff,DB: Step 2 — Confirm (actual import)
        Staff->>API: POST /imports/confirm<br/>{token: uuid}
        API->>Cache: Retrieve import_{uuid}
        alt Token expired or invalid
            API-->>Staff: 422 "Import session expired or invalid token"
        else Cache hit
            API->>FS: Read stored file
            API->>DB: Run import (StudentImport / TuitionInvoiceImport)<br/>Actual DB writes
            API->>Cache: Delete import_{uuid}
            API->>FS: Delete stored file
            API-->>Staff: {message: "Import completed", created: N}
        end
    end
```

### Template downloads

| Endpoint | Description |
|---|---|
| `GET /imports/template/students` | Returns `template-students.xlsx` for staff to fill |
| `GET /imports/template/tuition-invoices` | Returns `template-tuition-invoices.xlsx` for staff to fill |

## 10. Excel export flow

Filtered exports to `.xlsx` files. All three export types share the same endpoint pattern with a `{type}` parameter.

```mermaid
flowchart TD
    A["GET /exports/{type}"] --> B{"type parameter"}

    B -->|"students"| C["StudentExport<br/>Filters: school_class, status"]
    B -->|"tuition-invoices"| D["TuitionInvoiceExport<br/>Filters: period, fee_type, status, student_id"]
    B -->|"payments"| E["PaymentRecordExport<br/>Filters: date_from, date_to, status, student_id"]
    B -->|other| F["422 — Invalid export type"]

    C --> G["Excel::download() → .xlsx"]
    D --> G
    E --> G
    G --> H["Response: file download<br/>Filename: {type}-export-YYYY-MM-DD.xlsx"]
```

### Export filters by type

| Export | Filters |
|---|---|
| `students` | `school_class` — class name, `status` — active/inactive |
| `tuition-invoices` | `period` — YYYY-MM, `fee_type`, `status`, `student_id` |
| `payments` | `date_from`, `date_to` — date range, `status`, `student_id` |

## 11. Document generation flow

Bills and receipts can be viewed as HTML or downloaded as PDFs. Receipts also have a temporary public share URL (7-day expiry via signed route).

```mermaid
flowchart TD
    subgraph Bills["Bill documents — pending_payment invoices only"]
        A["GET /invoices/{id}/bill<br/>HTML view"]
        B["GET /invoices/{id}/bill/download<br/>PDF download"]
    end

    subgraph Receipts["Receipt documents — paid attempts only"]
        C["GET /payment-attempts/{id}/receipt<br/>HTML view"]
        D["GET /payment-attempts/{id}/receipt/download<br/>PDF download"]
        E["GET /payment-attempts/{id}/receipt/share<br/>Returns signed URL + download_url<br/>Expires in 7 days"]
    end

    subgraph Public["Public receipt access — no auth required"]
        F["GET /public/receipt/{id}<br/>HTML view (signed URL)"]
        G["GET /public/receipt/{id}/download<br/>PDF download (signed URL)"]
    end

    E -->|"Staff shares URL"| H["Parent opens URL<br/>No login required"]
    H --> F
    H --> G

    A --> I["DocumentService.generateBillHtml()"]
    B --> J["DocumentService.generateBillPdf()"]
    C --> K["DocumentService.generateReceiptHtml()"]
    D --> L["DocumentService.generateReceiptPdf()"]
```

### Document status guards

| Document | Required status | HTTP error if wrong |
|---|---|---|
| Bill (HTML + PDF) | `pending_payment` | 404 |
| Receipt (HTML + PDF) | `paid` | 404 |
| Share URL | `paid` | 404 |

## 12. Authentication flow

Sanctum personal access tokens (not SPA cookie auth). Every authenticated request uses `Authorization: Bearer {token}`.

```mermaid
sequenceDiagram
    participant Client
    participant API as Backend API
    participant DB as Database

    rect rgb(240, 255, 240)
        Note over Client,DB: Register
        Client->>API: POST /auth/register<br/>{name, email, password}
        API->>DB: User::create()
        API->>API: $user->createToken('api-token')
        API-->>Client: 201 {user, token}
    end

    rect rgb(240, 248, 255)
        Note over Client,DB: Login
        Client->>API: POST /auth/login<br/>{email, password}
        API->>DB: User::where('email')
        API->>API: Hash::check()
        API->>API: $user->createToken('api-token')
        API-->>Client: 200 {user, token}
    end

    rect rgb(255, 248, 240)
        Note over Client,DB: Logout
        Client->>API: POST /auth/logout<br/>Authorization: Bearer {token}
        API->>API: $request->user()->currentAccessToken()->delete()
        API-->>Client: 200 {message: "Logged out"}
    end

    rect rgb(248, 240, 255)
        Note over Client,DB: Get current user
        Client->>API: GET /auth/me<br/>Authorization: Bearer {token}
        API-->>Client: 200 {user}
    end
```

## 13. Webhook processing flow (enhanced)

Midtrans sends a server-to-server notification when a payment status changes. The backend verifies the signature, records the notification, maps the status, and processes all linked invoices in a single DB transaction.

```mermaid
sequenceDiagram
    participant MT as Midtrans
    participant API as Backend API
    participant DB as Database

    MT->>API: POST /midtrans/webhook<br/>{order_id, status_code, gross_amount,<br/>signature_key, transaction_status, fraud_status}

    API->>API: MidtransService.verifySignature()<br/>HMAC check of order_id + status_code + gross_amount

    API->>DB: PaymentNotification::create()<br/>(always logged, even if signature invalid)

    alt Signature invalid
        API-->>MT: 400 "Invalid signature"
    else Signature valid
        API->>DB: PaymentAttempt::where('provider_order_id')
        alt Attempt not found
            API-->>MT: 404 "Payment attempt not found"
        else Attempt found
            API->>API: isTerminal() check
            alt Already terminal (paid/expired/cancelled)
                API-->>MT: 200 "Already in terminal state" (idempotent)
            else Can transition
                API->>API: mapMidtransStatus(transaction_status, fraud_status)
                Note over API: capture + fraud accept → paid<br/>settlement → paid<br/>pending → pending<br/>expire → expired<br/>cancel → cancelled<br/>deny → failed
                API->>DB: BEGIN TRANSACTION
                alt New status = paid
                    API->>DB: Transition all linked TuitionInvoices → paid<br/>(skip any already terminal)
                end
                API->>DB: Transition PaymentAttempt → new status
                API-->>MT: 200 "OK"
            end
        end
    end
```

### Midtrans status mapping

| `transaction_status` | `fraud_status` | Internal status |
|---|---|---|
| `capture` | `accept` | `paid` |
| `settlement` | *(any)* | `paid` |
| `pending` | *(any)* | `pending` |
| `expire` | *(any)* | `expired` |
| `cancel` | *(any)* | `cancelled` |
| `deny` | *(any)* | `failed` |

## 14. Scheduled invoice generation

A monthly Artisan command runs via the Laravel scheduler to auto-generate SPP invoices for all active students. Uses `firstOrCreate` so re-runs are idempotent.

```mermaid
flowchart TD
    A["Scheduler triggers<br/>app:generate-monthly-invoices<br/>Runs monthly via Schedule::command()"] --> B["Determine current period<br/>period = now().format('Y-m')"]
    B --> C["Query active students<br/>Student::where('status', 'active')"]
    C --> D{"For each student"}
    D --> E["TuitionInvoice::firstOrCreate<br/>(student_id, period, fee_type: spp)<br/>generation_source: scheduled"]
    E --> F{"Already exists in non-draft?"}
    F -->|Yes| G["Skip — invoice preserved"]
    F -->|No / newly created| H["Invoice created with status: draft<br/>amount = student->monthly_fee<br/>due_date = YYYY-MM-DD (capped at 28th)"]
    D --> I["Output: Generated/verified N invoices"]
```

### Command reference

```bash
# Run manually (e.g. for testing or backfill)
php artisan app:generate-monthly-invoices

# Automatic schedule — runs on the 1st of every month
Schedule::command(GenerateMonthlyInvoices::class)->monthly();
```
