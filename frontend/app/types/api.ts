/**
 * Shapes returned by the Laravel API Resources (backend/app/Http/Resources).
 *
 * Money is always a decimal STRING ("150000000.00"), never a number: DECIMAL(20,2) holds
 * values beyond Number.MAX_SAFE_INTEGER, and binary floats cannot represent most decimals.
 * Strings go straight to Intl.NumberFormat for display and back to the API unchanged; any
 * arithmetic stays on the server.
 */

export type CurrencyCode = 'LAK' | 'USD' | 'THB'

/** Decimal string with up to 2 decimals, e.g. "21850.50". */
export type DecimalString = string

/** Exchange rate as stored, DECIMAL(20,8), e.g. "21850.00000000". */
export type RateString = string

/** Calendar date "YYYY-MM-DD", no time zone. */
export type IsoDate = string

/** ISO 8601 timestamp with offset. */
export type IsoDateTime = string

export interface Money {
  amount: DecimalString
  currency: CurrencyCode
}

export interface OutstandingMoney extends Money {
  /** "estimated" while the invoice (actual_amount) is unknown. */
  basis: 'estimated' | 'actual'
}

export type WeddingStatus = 'planning' | 'completed'
export type CategoryType = 'revenue' | 'expense'
export type RevenueStatus = 'expected' | 'received'
export type ExpenseStatus = 'planned' | 'booked' | 'partially_paid' | 'paid' | 'cancelled'
export type PaymentMethod = 'cash' | 'bank_transfer' | 'other'
export type BudgetHealth = 'green' | 'amber' | 'red'

export interface User {
  id: number
  name: string
  email: string
}

export interface Wedding {
  id: number
  name: string
  wedding_date: IsoDate | null
  status: WeddingStatus
  base_currency: CurrencyCode
  budget_target: Money
  notes: string | null
  created_at: IsoDateTime | null
  updated_at: IsoDateTime | null
}

export interface Category {
  id: number
  type: CategoryType
  name: string
  /** In the wedding's base currency; expense categories only. */
  planned_amount: Money | null
}

/** Minimal category embedded in revenue and expense records. */
export interface CategorySummary {
  id: number
  type: CategoryType
  name: string
}

export interface ExchangeRate {
  id: number
  from_currency: CurrencyCode
  to_currency: CurrencyCode
  rate: RateString
  effective_date: IsoDate
}

export interface Revenue {
  id: number
  category_id: number
  category?: CategorySummary
  source: string
  amount: Money
  exchange_rate: RateString
  base_amount: Money
  status: RevenueStatus
  expected_date: IsoDate | null
  received_date: IsoDate | null
  payment_method: PaymentMethod | null
  notes: string | null
  created_at: IsoDateTime | null
  updated_at: IsoDateTime | null
}

export interface Receipt {
  original_name: string
  mime_type: string
  size: number
}

export interface ExpensePayment {
  id: number
  expense_id: number
  amount: Money
  exchange_rate: RateString
  base_amount: Money
  paid_date: IsoDate
  payment_method: PaymentMethod | null
  notes: string | null
  receipt: Receipt | null
  created_at: IsoDateTime | null
  updated_at: IsoDateTime | null
}

export interface Expense {
  id: number
  category_id: number
  category?: CategorySummary
  description: string
  vendor_name: string | null
  currency: CurrencyCode
  exchange_rate: RateString
  estimated_amount: Money
  actual_amount: Money | null
  paid_amount: Money
  base_estimated_amount: Money
  base_actual_amount: Money | null
  base_paid_amount: Money
  outstanding_amount: OutstandingMoney
  is_overpaid: boolean
  status: ExpenseStatus
  due_date: IsoDate | null
  notes: string | null
  /** Present on GET /expenses/{id} only. */
  payments?: ExpensePayment[]
  created_at: IsoDateTime | null
  updated_at: IsoDateTime | null
}

export interface MoneyPair {
  /** Total in the original currency. */
  amount: Money
  /** The same records' snapshotted base-currency total. */
  base: Money
}

export interface Dashboard {
  as_of: IsoDate
  timezone: string
  base_currency: CurrencyCode
  summary: {
    budget_target: Money
    revenue: { expected: Money, received: Money, total: Money }
    expenses: { estimated: Money, actual: Money, paid: Money, committed: Money }
    remaining_budget: Money
    net_cash_position: Money
    funding_gap: Money
    secured_gap: Money
    planned_total: Money
    unallocated_budget: Money
  }
  per_currency: Array<{
    currency: CurrencyCode
    revenue: Record<RevenueStatus, MoneyPair>
    expenses: Record<'estimated' | 'actual' | 'paid' | 'committed', MoneyPair>
  }>
  categories: Array<{
    id: number
    name: string
    planned: Money | null
    estimated: Money
    actual: Money
    paid: Money
    committed: Money
    /** Decimal string, 2 decimals; null without a plan. */
    used_percent: DecimalString | null
    health: BudgetHealth | null
  }>
  expense_breakdown: Array<{
    category_id: number
    name: string
    committed: Money
    share_percent: DecimalString
  }>
  cash_flow: Array<{
    /** "YYYY-MM" */
    month: string
    inflow: Money
    outflow: Money
    net: Money
    cumulative: Money
  }>
  upcoming_payments: Array<{
    expense_id: number
    description: string
    vendor_name: string | null
    category: { id: number, name: string }
    status: ExpenseStatus
    due_date: IsoDate
    outstanding_amount: OutstandingMoney
  }>
}

/** Single resource envelope. */
export interface ResourceResponse<T> {
  data: T
}

/** Collection without pagination (categories, payments). */
export interface CollectionResponse<T> {
  data: T[]
}

/** Laravel LengthAwarePaginator envelope. */
export interface PaginatedResponse<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

/** Body of a 422 response. */
export interface ValidationErrorBody {
  message: string
  errors: Record<string, string[]>
}
