# Wedding Budget Planner (MVP)

## Role
You are a senior full-stack engineer. Build a production-ready **Wedding Budget Planner (MVP)**
that tracks REVENUE (income) and EXPENSES with a summary DASHBOARD, supporting multiple
currencies. Follow clean architecture, security best practices, and write clear code comments.
Explain key trade-offs as you go.

## Tech Stack
- Backend: Laravel 13 on PHP 8.5 or newer, REST API (API only, no Blade views)
- Database: MySQL 8.x or newer
- Auth: Laravel Sanctum (SPA cookie-based auth)
- Frontend: Nuxt 4 (Vue 3, Composition API, `<script setup lang="ts">`, TypeScript),
  using the Nuxt 4 `app/` directory structure
- Runtime / package manager: Node.js 24 LTS, pnpm (frontend), Composer (backend)
- UI: Nuxt UI (already installed, includes Tailwind CSS). Use Nuxt UI components
  (UTable, UForm, UInput, USelect, UModal, UCard, UBadge, UButton) instead of building
  custom equivalents. Only create custom components where Nuxt UI has no fit.
- State: Pinia
- Charts: Chart.js via vue-chartjs
- Form validation: Nuxt UI `UForm` with Zod schemas
- Testing: Pest (backend), Vitest (frontend)
- Tooling: Laravel Pint (backend), ESLint with Nuxt stylistic rules, no Prettier (frontend),
  Docker (docker-compose with PHP 8.5 image)

## Project Structure (IMPORTANT)
Backend and frontend are separate apps in separate folders inside one repository.
Both are ALREADY scaffolded — do not re-create them.

```
wedding-budget/
├── backend/              # Laravel 13 API only
├── frontend/             # Nuxt 4 + Nuxt UI app (talks to backend via REST API only)
├── docker/               # shared config (nginx, mysql init scripts)
├── docker-compose.yml    # runs mysql, backend, frontend together
├── CLAUDE.md             # this file
└── README.md             # how to install and run everything
```

Rules:
- Never mix code between folders; the frontend only communicates with the backend via HTTP
- Each folder has its own dependencies, `.env`, and tests
- Backend runs at http://localhost:8000, frontend at http://localhost:3000
- Configure CORS, `SANCTUM_STATEFUL_DOMAINS`, and session domain so Sanctum cookie auth
  works between these two origins

## Scope (MVP only — do NOT add vendors, guests, or collaborators)

### 1. Currency Rules (IMPORTANT)
- Supported currencies: **LAK (default/base)**, **USD**, **THB**
- PHP backed enum `Currency` with code, symbol (₭, $, ฿), display decimals, and locale
  - LAK: ₭, 0 display decimals
  - USD: $, 2 decimals
  - THB: ฿, 2 decimals
- Each wedding has a `base_currency` (default LAK). Dashboard totals are in base currency.
- Each revenue/expense record can be in LAK, USD, or THB (default = base currency)
- Exchange rates:
  - `exchange_rates` table: from_currency, to_currency, rate, effective_date
  - Managed manually by the user in Settings (no external API in MVP); define an
    `ExchangeRateProvider` interface so an API source can be added later
  - On save, SNAPSHOT the rate: store `amount`, `currency`, `exchange_rate`, `base_amount`
  - Historical records must NOT change when rates are updated later
  - Same currency as base → rate = 1
- Amounts: `DECIMAL(20,2)`; rates: `DECIMAL(20,8)`. Never use FLOAT.
- Precise money math in PHP (BCMath or `brick/money` — pick one and justify);
  define one consistent rounding rule for conversions

### 2. Wedding (budget container)
- A user can create one or more weddings
- Fields: name, wedding_date, base_currency (default LAK), budget_target (base currency), notes
- Users only see their own weddings

### 3. Revenue (Income)
- Default categories (seeded, user can add custom ones):
  Family Contribution, Guest Gifts, Personal Savings, Sponsorship, Loan, Other
- Fields: category, source, amount, currency, exchange_rate, base_amount,
  status (expected / received), received_date,
  payment_method (cash / bank_transfer / other), notes

### 4. Expenses
- Default categories (seeded, user can add custom ones):
  Venue, Catering, Attire, Photography & Video, Decoration & Flowers,
  Music & Entertainment, Invitations, Rings & Jewelry, Transportation,
  Ceremony, Honeymoon, Miscellaneous
- Fields: category, description, vendor_name (plain text), currency, exchange_rate,
  estimated_amount, actual_amount, paid_amount (+ base-currency equivalents),
  status (planned / partially_paid / paid / cancelled), due_date, notes
- Optional receipt upload (image/PDF)

### 5. Dashboard (totals in base currency, LAK by default)
- Summary cards: Budget Target; Total Revenue (expected vs received);
  Total Expenses (estimated vs actual vs paid);
  Remaining Budget = Budget Target − Actual Expenses;
  Net Cash Position = Received Revenue − Paid Expenses
- Per-currency breakdown (original LAK/USD/THB totals) next to the converted base total
- Charts: Budget vs Actual per category (bar); Expense breakdown (doughnut);
  Monthly cash flow, revenue in vs expenses out (line)
- Lists: unpaid expenses due in the next 30 days; over-budget categories highlighted
- CSV export of revenue and expenses (original amount, currency, rate, base amount)

## Backend Architecture (Laravel 13 / PHP 8.5)
- `app/Http/Controllers/Api/V1` – thin controllers only
- `app/Http/Requests` – Form Request validation on every write endpoint
- `app/Http/Resources` – API Resources for all responses (never raw models)
- `app/Services` – `DashboardSummaryService`, `CurrencyConversionService`
- `app/Contracts/ExchangeRateProvider` + `ManualExchangeRateProvider`
- `app/Policies` – ownership authorization for Wedding, Revenue, Expense, ExchangeRate
- `app/Enums` – `Currency`, statuses, payment methods
- Dashboard aggregates via SQL `SUM`/`GROUP BY` on `base_*` columns
- DB transactions when saving records with conversions
- Indexes on foreign keys, `currency`, `status`, `due_date`, `received_date`
- Soft deletes on revenue and expenses
- `/api/v1/...` versioning; consistent JSON error format; paginated list endpoints
  with filtering (category, status, currency, date range) and sorting
- Factories + seeders with realistic demo data in LAK, USD, THB and sample rates
- Use PHP 8.5 features only where they genuinely improve clarity

## Frontend Architecture (Nuxt 4 + Nuxt UI)
- Nuxt 4 `app/` directory: `app/pages`, `app/components`, `app/composables`,
  `app/layouts`, `app/middleware`
- Pages: Login, Register, Wedding list/create, Dashboard, Revenue, Expenses,
  Settings (including Exchange Rates)
- Composables: `useApi` (shared `$fetch` wrapper: Sanctum CSRF cookie, credentials,
  error toasts), `useRevenue`, `useExpenses`, `useDashboard`, `useCurrency`
  (`Intl.NumberFormat` per currency)
- Pinia only for global state (auth user, current wedding, base currency)
- Custom components only where Nuxt UI has no fit: MoneyInput (currency selector
  defaulting to LAK, thousand separators), MoneyDisplay (original + base equivalent),
  CurrencySelect, SummaryCard
- Non-base currency in a form → show current rate and live LAK preview
  (backend remains source of truth)
- Mobile-first, responsive; dark mode support
- Route middleware: `auth` and `guest`

## Security Requirements
- Sanctum SPA auth with CSRF protection; secure, HttpOnly, SameSite cookies
- Policy check on EVERY wedding-scoped request — prevent IDOR
- Scoped route model binding (`/weddings/{wedding}/expenses/{expense}`)
- Never trust client-sent `exchange_rate` or `base_amount`; backend computes them
- `$fillable` on models; only `$request->validated()`
- Rate limiting on login/register and write endpoints
- Receipts: validate MIME + size, private disk, temporary signed URLs
- No `v-html` with user content
- CORS and `SANCTUM_STATEFUL_DOMAINS` restricted to the frontend origin
- Secrets only in `.env`; provide `.env.example` for both apps

## Testing
- Pest: auth, IDOR/authorization, CRUD, validation, currency conversion
  (LAK↔USD↔THB), rate snapshot immutability, large LAK values, dashboard math
- Vitest: currency formatting per currency, key composables

## Deliverables (in this order)
1. Folder structure review for backend and frontend
2. ERD (Mermaid) + MySQL migrations with indexes and foreign keys
3. Models, Enums (including `Currency`), relationships, factories, seeders
4. Currency conversion service + exchange rate management
5. Policies, Form Requests, API Resources, Controllers, `routes/api.php`
6. `DashboardSummaryService` + dashboard endpoint
7. Pest tests
8. Nuxt setup: `nuxt.config.ts`, `useApi`, auth flow, layouts, middleware
9. Pages and components (Revenue, Expenses, Dashboard with charts, Exchange Rates)
10. Docker setup + README (install, run, test, deploy notes)

## Rules
- Production-ready code only — no placeholders or "TODO implement"
- Latest stable conventions for Laravel 13, PHP 8.5, Nuxt 4, Nuxt UI;
  note anything that changed from earlier versions
- Comments explain WHY, not just what
- After each major section, list trade-offs and alternatives considered
- Ask before making assumptions that change the data model
- Work one deliverable at a time; run tests after each, then stop and wait for my confirmation
- `backend/CLAUDE.md` contains Laravel's official coding guidelines. Follow them for
  Laravel conventions. If anything conflicts with this file on scope, architecture,
  security, or currency rules, THIS file wins.
- Commit messages follow Conventional Commits (feat:, fix:, test:, chore:, docs:)
