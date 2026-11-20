# Business rules

Decisions agreed during development. Code and tests must follow this file; change it
first when a rule changes. Schema-level guarantees are in `database/ERD.md`.

## Currencies and exchange rates

- Supported currencies: LAK (default base), USD, THB (`App\Enums\Currency`).
- Each wedding has one `base_currency`. Dashboard totals are always in it.
- **Rates are stored foreign → base only**: `from_currency` is a non-base currency and
  `to_currency` must equal the wedding's `base_currency`. Meaning: 1 `from_currency` = `rate` base.
  No inverse or cross rates are derived.
- **Rate is always > 0** (validation and DB `CHECK`).
- One rate per pair per `effective_date`.
- **Same currency as base → rate 1**, without a rate lookup.
- **Lookup**: the latest rate with `effective_date` on or before the transaction date.
  **Never fall back to a later rate.** If none exists, the save fails with a 422 validation
  error that names the currency pair and the date (`App\Exceptions\MissingExchangeRateException`).
- **`base_currency` can only change** while the wedding has no revenues and no expenses
  (soft-deleted ones included) and no exchange rates. Rates point to the old base, so the user
  deletes them first; nothing is deleted implicitly.

## Rate snapshots

- On save, each record stores `exchange_rate` and its `base_*` amounts. The backend computes
  both; client-sent values are never accepted (not fillable).
- Snapshots are immutable history: editing or deleting a rate in Settings never changes
  an existing record.
- Transaction date used for the lookup, and when a new snapshot is taken:

| Record | Transaction date | New snapshot when | Amount-only edits |
|---|---|---|---|
| Revenue | `received_date`, else `expected_date`, else the save date | currency or that date changes (incl. expected → received) | reuse the stored rate |
| Expense (estimated / actual) | save date (creation day) | currency changes | reuse the stored rate, incl. setting `actual_amount` |
| Expense payment | `paid_date` | `paid_date` changes | reuse the stored rate |

- A `received` revenue requires `received_date`.

## Rounding

- `base = amount × rate`, multiplied at full precision with BCMath (`BcMath\Number`), never floats.
- Rounded once, **half away from zero, to 2 decimals** for `DECIMAL(20,2)` storage
  (0.005 → 0.01). Rates are used as stored (`DECIMAL(20,8)`) and never re-rounded.
- Totals are SUMs of stored base amounts and are never converted again, so a total always
  equals the sum of its lines.
- Display rounding (0 decimals for LAK) happens only in the frontend.
- Single implementation: `App\Services\CurrencyConversionService::convert()`.

## Expenses and payments

- **Same-currency payments**: a payment's currency must equal its expense's currency
  (422 validation error; composite FK in the DB). An expense's currency cannot change once
  it has payments.
- **Only one write path**: `App\Actions\Expenses\ManageExpensePayment` creates, updates and
  deletes payments, in one transaction with the expense row locked. It keeps the cached
  `paid_amount` / `base_paid_amount` equal to `SUM()` of the payments.
- **Status**: `planned`, `booked`, `cancelled` are set by the user. `partially_paid` and `paid`
  are derived from payments:
  - paid ≥ `actual_amount` → `paid`;
  - 0 < paid < actual, or any payment while `actual_amount` is unknown → `partially_paid`;
  - last payment removed → back to the user status, or `booked` if the status was derived;
  - `cancelled` stays `cancelled` whatever is paid.
- **Derived in the expense's own currency** (`paid_amount` vs `actual_amount`), never from
  base amounts, so exchange-rate movements cannot make a fully paid expense look over- or
  underpaid. Base amounts are only for dashboard totals.
- **Overpayment** (paid > actual) is accepted: status `paid`, `is_overpaid` = true.
- **Outstanding** = (`actual_amount`, or `estimated_amount` until the actual is set) − paid,
  never below 0, in the expense currency.
- **Cancelled expenses**: their payments still count as paid (forfeited deposit), but
  outstanding = 0, and they are excluded from upcoming payments and commitments on the dashboard.

## Dashboard

Implemented by `App\Services\DashboardSummaryService` (`GET /api/v1/weddings/{wedding}/dashboard`).

- All totals are in the wedding's base currency and are SQL `SUM`s of the stored `base_*`
  snapshots, never re-converted. Soft-deleted revenues and expenses (and payments of soft-deleted
  expenses) are excluded everywhere.
- A fixed number of queries (5), whatever the number of records; no record rows are loaded
  except the upcoming-payments list itself.
- **Cancelled expenses** count only what was paid on them (forfeited deposit): their estimated,
  actual and committed figures equal their paid amount, and the rest of their balance is dropped.
  They never appear in upcoming payments.
- **Committed** = `actual_amount`, or `estimated_amount` until the actual is known
  (cancelled: paid amount).

| Figure | Definition |
|---|---|
| Revenue expected / received / total | SUM `base_amount` by status; total = expected + received |
| Expenses estimated / actual / paid / committed | SUM of the base columns, cancelled rule above; paid includes overpayments as paid |
| Remaining budget | `budget_target` − actual expenses (may be negative) |
| Net cash position | received revenue − paid expenses |
| Funding gap ("if all pledges arrive") | committed expenses − (expected + received revenue). Positive = money still missing, negative = surplus |
| Secured gap ("money received only") | committed expenses − received revenue; pledges not yet received are ignored. Positive = money still missing, negative = surplus |
| Unallocated budget | `budget_target` − SUM of expense categories' `planned_amount` (may be negative) |

- **Per-currency breakdown**: the same revenue and expense figures per original currency, with
  their base equivalent. Every supported currency is listed, with zeros when unused.
- **Budget vs actual per expense category**: planned, estimated, actual, paid and committed.
  `used_percent` = committed / planned (2 decimals, `null` without a plan). **Health** compares
  committed with planned: green < 90%, amber 90–100% (both inclusive), red > 100%; no plan → no
  health; a plan of 0 is red as soon as anything is committed.
- **Unplanned spending**: expense categories with committed > 0 and no plan (`planned_amount`
  null). They have no health and are never listed as over budget; a plan of 0 is a plan (red).
- **Expense breakdown**: committed per category (categories with nothing committed omitted),
  largest committed first (ties by name), with a share rounded per slice to 2 decimals (shares
  may add up to 99.99–100.01).
- **Monthly cash flow**: inflow = received revenue by `received_date`; outflow = payments by
  `paid_date` at their own rate snapshot (payments on cancelled expenses included). Months
  between the first and last movement are filled with zeros; `cumulative` ends at the net cash
  position.
- **Upcoming payments**: expenses not cancelled, `due_date` from today to today + 30 days
  (inclusive), with an outstanding balance > 0; sorted by `due_date`. Outstanding is in the
  expense currency, `basis` `estimated` while `actual_amount` is null. Overdue balances are not
  part of this list.
- **"Today"** is the calendar date in `Asia/Vientiane`, whatever the server time zone.

## CSV export

- `GET /api/v1/weddings/{wedding}/exports/revenues` and `/exports/expenses`: policy-checked like
  every wedding endpoint, limited to 10 per minute per user, soft-deleted records excluded.
- Streamed in chunks, UTF-8 with a BOM (Excel shows Lao text correctly), CRLF line endings.
- File name `<wedding name>-<revenues|expenses>-<YYYY-MM-DD>.csv` (date in Lao time). Lao text is
  kept through `filename*` (RFC 6266); `/ \ : * ? " < > |`, control characters and `%` are
  replaced with `-`, and runs of spaces, dots and hyphens become one `-`. The ASCII `filename` fallback is a slug of the name, or `wedding-<id>` when nothing
  ASCII is left. `Content-Disposition` is exposed through CORS so the SPA can name the download.
- Each row has the original amount, currency, snapshotted rate and base amount(s); expenses also
  carry paid and outstanding amounts with the outstanding basis.
- Formula injection: a cell starting with `=`, `+`, `-`, `@`, tab or carriage return is prefixed
  with `'`. Amounts are never negative, so numbers are not affected.

## Categories and deletion

- Default category names live in `App\Enums\CategoryType::defaultNames()` and are copied into
  each wedding when it is created; each wedding owns and can edit its copies.
- `planned_amount` (base currency) is for expense categories only.
- A record's `category_id` must belong to the same wedding and match the record type
  (revenue or expense). The write actions check this (422 on `category_id`, one message for
  every failure so foreign ids are never confirmed); a composite FK backs the same-wedding part.
- Deleting a category that still has revenues or expenses (soft-deleted included) fails with
  409 `category_in_use`; financial records are never deleted with it.
- Deleting a wedding goes through `App\Actions\Weddings\DeleteWedding`, which removes
  everything it owns in one transaction and its receipt files after the commit.

## Access and API behaviour

- Every wedding-scoped request is authorized against the wedding owner (policies, run as
  controller middleware before validation). Another user's wedding, record, payment, rate or
  category answers **404** with the same body as a missing id (`{"message": "Not found."}`),
  never 403, so ids are not confirmed. Nested ids must belong to their parent (scoped bindings).
- Duplicate-key races surface as the standard 422 `{message, errors}` body.
- Rate limits: login/register 5 per minute per email and 20 per minute per IP; writes 60 per
  minute per user; reads are not limited.
- Receipts (JPEG, PNG, WebP or PDF, max 5 MB, MIME type sniffed from the content) live on the
  private `receipts` disk. They are reachable only through a signed URL valid for 2 minutes,
  issued after the payment policy check, with a sanitized file name,
  `Cache-Control: private, no-store`, `nosniff` and `Referrer-Policy: no-referrer`. Images are
  shown inline under a strict sandbox CSP; PDFs are sent as `Content-Disposition: attachment`
  (downloaded, never rendered on the API origin). Signed URLs
  are never logged. The local disk's `storage/{path}` route is disabled.
- Amounts are JSON strings with their currency (`{"amount": "1500.00", "currency": "USD"}`);
  records expose both original and base amounts. `outstanding_amount.basis` is `estimated`
  until `actual_amount` is set, then `actual`.
