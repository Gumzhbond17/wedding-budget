# Entity Relationship Diagram

Domain tables for the Wedding Budget Planner MVP. Laravel framework tables
(`password_reset_tokens`, `sessions`, `cache`, `jobs`, `personal_access_tokens`) are omitted.

```mermaid
erDiagram
    users ||--o{ weddings : owns
    weddings ||--o{ categories : has
    weddings ||--o{ exchange_rates : "manual rates"
    weddings ||--o{ revenues : has
    weddings ||--o{ expenses : has
    categories ||--o{ revenues : "classifies (same wedding)"
    categories ||--o{ expenses : "classifies (same wedding)"
    expenses ||--o{ expense_payments : "paid by (same currency)"

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
        varchar status "planning|completed"
        char base_currency "LAK|USD|THB, default LAK, locked once records exist"
        decimal budget_target "20,2 base currency, >= 0"
        text notes "nullable"
    }

    categories {
        bigint id PK
        bigint wedding_id FK "NOT NULL, defaults copied on wedding create"
        varchar type "revenue|expense"
        varchar name "unique per wedding + type"
        decimal planned_amount "20,2 base currency, expense only, nullable"
    }

    exchange_rates {
        bigint id PK
        bigint wedding_id FK
        char from_currency "<> to_currency"
        char to_currency
        decimal rate "20,8 : 1 from = rate to, > 0"
        date effective_date
    }

    revenues {
        bigint id PK
        bigint wedding_id FK
        bigint category_id FK "(wedding_id, category_id)"
        varchar source
        decimal amount "20,2 original"
        char currency
        decimal exchange_rate "20,8 snapshot"
        decimal base_amount "20,2 snapshot"
        varchar status "expected|received"
        date expected_date "nullable"
        date received_date "nullable"
        varchar payment_method "cash|bank_transfer|other, nullable"
        text notes "nullable"
        timestamp deleted_at "soft delete"
    }

    expenses {
        bigint id PK
        bigint wedding_id FK
        bigint category_id FK "(wedding_id, category_id)"
        varchar description
        varchar vendor_name "nullable, plain text"
        char currency "fixed once payments exist"
        decimal exchange_rate "20,8 snapshot for estimate/actual"
        decimal estimated_amount "20,2"
        decimal actual_amount "20,2 nullable = not invoiced"
        decimal base_estimated_amount "20,2 snapshot"
        decimal base_actual_amount "20,2 snapshot, nullable"
        decimal paid_amount "cached SUM(payments.amount)"
        decimal base_paid_amount "cached SUM(payments.base_amount)"
        varchar status "planned|booked|partially_paid|paid|cancelled"
        date due_date "nullable"
        text notes "nullable"
        timestamp deleted_at "soft delete"
    }

    expense_payments {
        bigint id PK
        bigint expense_id FK "(expense_id, currency)"
        decimal amount "20,2"
        char currency "= expenses.currency"
        decimal exchange_rate "20,8 snapshot at payment"
        decimal base_amount "20,2 snapshot"
        date paid_date "cash-flow chart"
        varchar payment_method "cash|bank_transfer|other, nullable"
        varchar receipt_path "nullable, private disk"
        varchar receipt_original_name "nullable"
        varchar receipt_mime_type "nullable"
        int receipt_size "nullable, bytes"
        text notes "nullable"
    }
```

## Key rules encoded in the schema

| Rule | How |
|---|---|
| Exact money | `DECIMAL(20,2)` amounts, `DECIMAL(20,8)` rates. No `FLOAT`. |
| Valid values | MySQL `CHECK`: amounts `>= 0` (estimates may be 0), payment `amount > 0`, rates `> 0`, `from_currency <> to_currency`, `planned_amount` only on expense categories. |
| Historical immutability | Records and payments store `exchange_rate` + `base_*` snapshots; no FK to `exchange_rates`. |
| Consistent base totals | `weddings.base_currency` locked once revenue/expenses exist (enforced in Form Request). |
| Ownership / IDOR | Every domain row reaches `users` through `weddings.user_id`. Composite FK `(wedding_id, category_id) -> categories(wedding_id, id)` stops a record using another wedding's category. |
| Payment currency | Composite FK `(expense_id, currency) -> expenses(id, currency)`: payments share the expense currency, and the expense currency cannot change once payments exist (`ON UPDATE RESTRICT`). |
| Paid totals | `expenses.paid_amount` / `base_paid_amount` are cached sums, rewritten in the same transaction as any payment change. `partially_paid` / `paid` derive from `paid_amount` vs `actual_amount`. |
| Query speed | Composite indexes lead with `wedding_id`: `(wedding_id, status)`, `(wedding_id, currency)`, `(wedding_id, received_date)`, `(wedding_id, due_date)`; payments use `(expense_id, paid_date)`. |
| Rate lookup | Unique `(wedding_id, from_currency, to_currency, effective_date)` also serves "latest rate on or before date". |

## Delete rules

| Foreign key | On delete | Why |
|---|---|---|
| `categories.wedding_id`, `exchange_rates.wedding_id`, `revenues.wedding_id`, `expenses.wedding_id` | CASCADE | Owned by the wedding. |
| `expense_payments.(expense_id, currency)` | CASCADE | A payment has no meaning without its expense (only reached on force delete). |
| `revenues.(wedding_id, category_id)`, `expenses.(wedding_id, category_id)` | RESTRICT | Deleting a category must never delete financial records. |
| `weddings.user_id` | RESTRICT | Account removal must go through the wedding delete action, never a silent cascade. |

**Category delete** (`App\Actions\Categories\DeleteCategory`) attempts the `DELETE` and maps MySQL
error 1451 to `CategoryInUseException`, rendered as `409 {"code": "category_in_use"}`. Soft-deleted
records count as in use. Letting the FK decide is atomic; a "count, then delete" check would race.

**Wedding delete** (`App\Actions\Weddings\DeleteWedding`). Because of the RESTRICT category FKs, a plain
`DELETE FROM weddings` fails once records exist:

```
SQLSTATE[23000]: 1451 Cannot delete or update a parent row: a foreign key constraint fails
(`expenses`, CONSTRAINT `expenses_category_id_foreign` ... ON DELETE RESTRICT)
```

InnoDB reached `categories` (via `categories_wedding_id_foreign`) before `expenses`, and `RESTRICT` is
checked per row immediately, not at commit. The action therefore locks the wedding row and deletes in
one transaction, children first: payments, then expenses and revenues (query builder, so soft-deleted
rows are purged too), then categories, exchange rates and the wedding. Receipt files on the private
`receipts` disk are removed only after the commit.

Covered by `tests/Feature/Database/SchemaIntegrityTest.php` and `tests/Feature/Actions/`.
