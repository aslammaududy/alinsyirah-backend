# SIMDIK Al Insyirah — Architecture diagrams

Visual reference for the school tuition payment backend (Laravel + Midtrans). All diagrams use [Mermaid](https://mermaid.js.org/), which renders natively on GitHub, GitLab, Notion, and most markdown viewers and editors (VS Code with the Mermaid extension, Obsidian, etc.).

## 1. Tuition payment flow

How a single tuition payment moves from invoice to confirmed payment.

```mermaid
flowchart TD
    A["Tuition invoice created<br/>Generated monthly per student"]
    B["Bundle invoices for payment<br/>Staff selects invoices to pay"]
    C["Midtrans payment link sent<br/>Parent completes payment online"]
    D["Webhook confirms payment<br/>Signature verified automatically"]
    E["Invoice marked as paid<br/>Status updates across invoices"]

    A --> B --> C --> D --> E
```

## 2. Database schema

Core tables and relationships. The many-to-many link between invoices and payment attempts is what powers bundled payments (multiple months, or a full annual prepayment, in a single Midtrans link).

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
    }
    PAYMENT_ATTEMPTS {
        string provider_order_id
        string payment_url
        string status
        int discount_amount
    }
    PAYMENT_ATTEMPT_INVOICES {
        int allocated_amount
    }
    PAYMENT_NOTIFICATIONS {
        string transaction_status
        boolean signature_valid
    }
```

## 3. API surface structure

What's reachable without a token versus what sits behind Sanctum auth.

```mermaid
flowchart LR
    subgraph Public["Public access — no auth token"]
        direction TB
        P1["Register & login<br/>No auth required"]
        P2["Midtrans webhook<br/>Confirms payment"]
    end

    subgraph Auth["Authenticated — Sanctum bearer token"]
        direction TB
        A1["Students<br/>Manage records"]
        A2["Invoices<br/>View & create"]
        A3["Payments<br/>Bundle & pay"]
        A4["Prepayments<br/>Annual lump sum"]
    end
```

## 4. Frontend integration sequence

The call sequence a frontend client needs to follow, including the part that trips people up: the Midtrans webhook talks to the backend directly, never to the frontend, so the frontend has to poll for the final status.

```mermaid
sequenceDiagram
    participant FE as Frontend
    participant API as Backend API
    participant MT as Midtrans

    FE->>API: POST /auth/login
    API-->>FE: user + token
    FE->>API: GET /tuition-invoices (Bearer token)
    API-->>FE: list of invoices
    FE->>API: POST /payment-attempts/bundle
    API-->>FE: payment_url
    FE->>MT: Redirect parent to payment_url
    MT->>API: Webhook (server-to-server, signed)
    Note right of API: Frontend never sees this call
    FE->>API: GET /payment-attempts/{id} (poll)
    API-->>FE: status: paid
```
