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
| Revenue | `received_date`, else `expected_date`, else the save date | currency or that date changes | reuse the stored rate |
| Expense (estimated / actual) | save date (creation day) | currency changes | reuse the stored rate, incl. setting `actual_amount` |
| Expense payment | `paid_date` | `paid_date` changes | reuse the stored rate |

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

## Categories and deletion

- Default category names live in `App\Enums\CategoryType::defaultNames()` and are copied into
  each wedding when it is created; each wedding owns and can edit its copies.
- `planned_amount` (base currency) is for expense categories only.
- Deleting a category that still has revenues or expenses (soft-deleted included) fails with
  409 `category_in_use`; financial records are never deleted with it.
- Deleting a wedding goes through `App\Actions\Weddings\DeleteWedding`, which removes
  everything it owns in one transaction and its receipt files after the commit.
