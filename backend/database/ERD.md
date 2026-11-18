# Entity Relationship Diagram

Domain tables for the Wedding Budget Planner MVP. Laravel framework tables
(`password_reset_tokens`, `sessions`, `cache`, `jobs`, `personal_access_tokens`) are omitted.

```mermaid
erDiagram
    users ||--o{ weddings : owns
    weddings ||--o{ categories : "custom categories"
    weddings ||--o{ exchange_rates : "manual rates"
    weddings ||--o{ revenues : has
    weddings ||--o{ expenses : has
    categories ||--o{ revenues : classifies
    categories ||--o{ expenses : classifies

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        timestamp email_verified_at
    }

    weddings {
        bigint id PK
        bigint user_id FK
        varchar name
        date wedding_date "nullable"
        char base_currency "LAK|USD|THB, default LAK, locked once records exist"
        decimal budget_target "20,2 in base_currency"
        text notes "nullable"
    }

    categories {
        bigint id PK
        bigint wedding_id FK "NULL = seeded system default"
        varchar type "revenue|expense"
        varchar name
    }

    exchange_rates {
        bigint id PK
        bigint wedding_id FK
        char from_currency
        char to_currency
        decimal rate "20,8 : 1 from = rate to"
        date effective_date
    }

    revenues {
        bigint id PK
        bigint wedding_id FK
        bigint category_id FK "restrict on delete"
        varchar source
        decimal amount "20,2 original"
        char currency
        decimal exchange_rate "20,8 snapshot"
        decimal base_amount "20,2 snapshot"
        varchar status "expected|received"
        date received_date "nullable"
        varchar payment_method "cash|bank_transfer|other, nullable"
        text notes "nullable"
        timestamp deleted_at "soft delete"
    }

    expenses {
        bigint id PK
        bigint wedding_id FK
        bigint category_id FK "restrict on delete"
        varchar description
        varchar vendor_name "nullable, plain text"
        char currency
        decimal exchange_rate "20,8 snapshot"
        decimal estimated_amount "20,2"
        decimal actual_amount "20,2 nullable"
        decimal paid_amount "20,2 default 0"
        decimal base_estimated_amount "20,2 snapshot"
        decimal base_actual_amount "20,2 snapshot, nullable"
        decimal base_paid_amount "20,2 snapshot"
        varchar status "planned|partially_paid|paid|cancelled"
        date due_date "nullable"
        date paid_date "nullable, latest payment"
        varchar receipt_path "nullable, private disk"
        varchar receipt_original_name "nullable"
        varchar receipt_mime_type "nullable"
        int receipt_size "nullable, bytes"
        text notes "nullable"
        timestamp deleted_at "soft delete"
    }
```

## Key rules encoded in the schema

| Rule | How |
|---|---|
| Exact money | `DECIMAL(20,2)` amounts, `DECIMAL(20,8)` rates. No `FLOAT`. |
| Historical immutability | Records store `exchange_rate` + `base_*` snapshots; no FK to `exchange_rates`. |
| Consistent base totals | `weddings.base_currency` locked once revenue/expenses exist (enforced in Form Request). |
| Ownership / IDOR | Every domain row reaches `users` through `weddings.user_id`. |
| Category safety | `category_id` is `RESTRICT ON DELETE`; deleting a wedding cascades to its custom categories, rates and records. |
| Query speed | Composite indexes lead with `wedding_id`: `(wedding_id, status)`, `(wedding_id, currency)`, `(wedding_id, received_date)`, `(wedding_id, due_date)`, `(wedding_id, paid_date)`. |
| Rate lookup | Unique `(wedding_id, from_currency, to_currency, effective_date)` also serves "latest rate on or before date". |
