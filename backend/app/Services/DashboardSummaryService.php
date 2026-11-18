<?php

namespace App\Services;

use App\Enums\BudgetHealth;
use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Wedding;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard figures for one wedding, in its base currency unless stated otherwise.
 *
 * Every total is a SQL SUM of the stored base_* snapshots (never re-converted), so a total
 * always equals the sum of its lines and old records never move when rates change. The work
 * is a fixed QUERY_COUNT queries whatever the number of records: four GROUP BY aggregates and
 * the short list of upcoming payments. PHP only adds up the few aggregated rows, with BCMath.
 *
 * Cancelled expenses (docs/business-rules.md): their payments are real cash out (a forfeited
 * deposit), so for a cancelled expense the estimated, actual and committed figures all equal
 * what was paid; the rest of its balance is dropped. Soft-deleted records are excluded.
 */
final class DashboardSummaryService
{
    /** "Today" and the upcoming window follow the couple's local calendar, not the server's. */
    public const string TIMEZONE = 'Asia/Vientiane';

    /** Upcoming payments: due from today up to and including today + this many days. */
    public const int UPCOMING_DAYS = 30;

    public const int QUERY_COUNT = 5;

    /**
     * @return array{
     *     as_of: string,
     *     base_currency: Currency,
     *     summary: array<string, string|array<string, string>>,
     *     per_currency: list<array{currency: Currency, revenue: array<string, array{amount: string, base: string}>, expenses: array<string, array{amount: string, base: string}>}>,
     *     categories: list<array{id: int, name: string, planned: ?string, estimated: string, actual: string, paid: string, committed: string, used_percent: ?string, health: ?BudgetHealth}>,
     *     expense_breakdown: list<array{category_id: int, name: string, committed: string, share_percent: string}>,
     *     cash_flow: list<array{month: string, inflow: string, outflow: string, net: string, cumulative: string}>,
     *     upcoming_payments: Collection<int, Expense>,
     * }
     */
    public function summarize(Wedding $wedding): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $revenues = $this->revenueTotals($wedding);
        $expenses = $this->expenseTotals($wedding);
        $categories = $this->categoryTotals($wedding);

        $revenue = [
            'expected' => $this->sum($revenues->where('status', RevenueStatus::Expected->value), 'base_amount'),
            'received' => $this->sum($revenues->where('status', RevenueStatus::Received->value), 'base_amount'),
        ];
        $revenue['total'] = (string) (new Number($revenue['expected']) + new Number($revenue['received']));

        $expenseTotals = [];
        foreach (['estimated', 'actual', 'paid', 'committed'] as $measure) {
            $expenseTotals[$measure] = $this->sum($expenses, "base_{$measure}");
        }

        $budgetTarget = new Number($wedding->budget_target);
        $planned = $this->sum($categories, 'planned_amount');

        return [
            'as_of' => $today->toDateString(),
            'base_currency' => $wedding->base_currency,
            'summary' => [
                'budget_target' => $wedding->budget_target,
                'revenue' => $revenue,
                'expenses' => $expenseTotals,
                'remaining_budget' => (string) ($budgetTarget - new Number($expenseTotals['actual'])),
                'net_cash_position' => (string) (new Number($revenue['received']) - new Number($expenseTotals['paid'])),
                // Positive: money still missing. Negative: surplus.
                // Funding gap assumes every pledge (expected revenue) arrives; the secured gap
                // counts only money already received.
                'funding_gap' => (string) (new Number($expenseTotals['committed']) - new Number($revenue['total'])),
                'secured_gap' => (string) (new Number($expenseTotals['committed']) - new Number($revenue['received'])),
                'planned_total' => $planned,
                'unallocated_budget' => (string) ($budgetTarget - new Number($planned)),
            ],
            'per_currency' => $this->perCurrency($revenues, $expenses),
            ...$this->categoryFigures($categories, $expenseTotals['committed']),
            'cash_flow' => $this->cashFlow($wedding),
            'upcoming_payments' => $this->upcomingPayments($wedding, $today),
        ];
    }

    /**
     * Revenue sums per currency and status, in the original currency and in base.
     *
     * @return Collection<int, object{currency: string, status: string, amount: string, base_amount: string}>
     */
    private function revenueTotals(Wedding $wedding): Collection
    {
        return $wedding->revenues()->toBase()
            ->select('currency', 'status')
            ->selectRaw('SUM(amount) AS amount, SUM(base_amount) AS base_amount')
            ->groupBy('currency', 'status')
            ->get();
    }

    /**
     * Expense measures per currency, in the original currency and in base.
     *
     * @return Collection<int, object>
     */
    private function expenseTotals(Wedding $wedding): Collection
    {
        [$original, $originalBindings] = $this->expenseMeasures('', '');
        [$base, $baseBindings] = $this->expenseMeasures('', 'base_');

        return $wedding->expenses()->toBase()
            ->select('currency')
            ->selectRaw("{$original}, {$base}", [...$originalBindings, ...$baseBindings])
            ->groupBy('currency')
            ->get();
    }

    /**
     * Base-currency measures per expense category. The LEFT JOIN keeps categories that only
     * have a plan so far; trashed expenses are filtered in the join, not the WHERE, for that reason.
     *
     * @return Collection<int, object>
     */
    private function categoryTotals(Wedding $wedding): Collection
    {
        [$measures, $bindings] = $this->expenseMeasures('e.', 'base_');

        return DB::table('categories AS c')
            ->leftJoin('expenses AS e', fn ($join) => $join
                ->on('e.category_id', '=', 'c.id')
                ->on('e.wedding_id', '=', 'c.wedding_id')
                ->whereNull('e.deleted_at'))
            ->where('c.wedding_id', $wedding->getKey())
            ->where('c.type', CategoryType::Expense->value)
            ->select('c.id', 'c.name', 'c.planned_amount')
            ->selectRaw($measures, $bindings)
            ->groupBy('c.id', 'c.name', 'c.planned_amount')
            ->orderBy('c.name')
            ->orderBy('c.id')
            ->get();
    }

    /**
     * SUM expressions for estimated / actual / paid / committed, applying the cancelled rule:
     * a cancelled expense counts only what was paid on it.
     *
     * Committed is the actual amount, or the estimate until the invoice is known.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function expenseMeasures(string $table, string $prefix): array
    {
        $paid = "{$table}{$prefix}paid_amount";
        $whenCancelled = "CASE WHEN {$table}status = ? THEN {$paid} ELSE";

        $sql = implode(', ', [
            "SUM({$whenCancelled} {$table}{$prefix}estimated_amount END) AS {$prefix}estimated",
            "SUM({$whenCancelled} {$table}{$prefix}actual_amount END) AS {$prefix}actual",
            "SUM({$paid}) AS {$prefix}paid",
            "SUM({$whenCancelled} COALESCE({$table}{$prefix}actual_amount, {$table}{$prefix}estimated_amount) END) AS {$prefix}committed",
        ]);

        return [$sql, array_fill(0, 3, ExpenseStatus::Cancelled->value)];
    }

    /**
     * Original-currency totals next to their base equivalent, for every supported currency
     * (zeros included, so the UI always shows the same rows).
     *
     * @param  Collection<int, object>  $revenues
     * @param  Collection<int, object>  $expenses
     * @return list<array{currency: Currency, revenue: array<string, array{amount: string, base: string}>, expenses: array<string, array{amount: string, base: string}>}>
     */
    private function perCurrency(Collection $revenues, Collection $expenses): array
    {
        return array_map(function (Currency $currency) use ($revenues, $expenses): array {
            $revenueRows = $revenues->where('currency', $currency->value);
            $expenseRows = $expenses->where('currency', $currency->value);

            $revenue = [];
            foreach (RevenueStatus::cases() as $status) {
                $rows = $revenueRows->where('status', $status->value);
                $revenue[$status->value] = ['amount' => $this->sum($rows, 'amount'), 'base' => $this->sum($rows, 'base_amount')];
            }

            $spent = [];
            foreach (['estimated', 'actual', 'paid', 'committed'] as $measure) {
                $spent[$measure] = ['amount' => $this->sum($expenseRows, $measure), 'base' => $this->sum($expenseRows, "base_{$measure}")];
            }

            return ['currency' => $currency, 'revenue' => $revenue, 'expenses' => $spent];
        }, Currency::cases());
    }

    /**
     * Budget vs actual per category and the breakdown chart, from the same aggregated rows.
     *
     * Health and "used" compare the committed amount with the plan, so a category whose quotes
     * already exceed the plan turns red before the invoices arrive.
     *
     * @param  Collection<int, object>  $rows
     * @return array{categories: list<array<string, mixed>>, expense_breakdown: list<array<string, mixed>>}
     */
    private function categoryFigures(Collection $rows, string $totalCommitted): array
    {
        $total = new Number($totalCommitted);
        $categories = [];
        $breakdown = [];

        foreach ($rows as $row) {
            $committed = new Number($row->base_committed ?? '0.00');
            $planned = $row->planned_amount === null ? null : new Number($row->planned_amount);

            $categories[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'planned' => $row->planned_amount,
                'estimated' => $row->base_estimated ?? '0.00',
                'actual' => $row->base_actual ?? '0.00',
                'paid' => $row->base_paid ?? '0.00',
                'committed' => (string) $committed,
                'used_percent' => $planned === null || $planned == 0 ? null : (string) ($committed * 100 / $planned)->round(2),
                'health' => BudgetHealth::of($committed, $planned),
            ];

            if ($committed > 0) {
                $breakdown[] = [
                    'category_id' => (int) $row->id,
                    'name' => $row->name,
                    'committed' => (string) $committed,
                    // Rounded per slice, so the shares may add up to 99.99 or 100.01.
                    'share_percent' => (string) ($committed * 100 / $total)->round(2),
                ];
            }
        }

        return ['categories' => $categories, 'expense_breakdown' => $breakdown];
    }

    /**
     * Cash in (received revenue on received_date) against cash out (each payment on its
     * paid_date, at its own rate snapshot), per month, with no gaps between the first and
     * last month so the line chart's x-axis is continuous.
     *
     * Payments on cancelled expenses count (money left); payments on trashed expenses do not.
     *
     * @return list<array{month: string, inflow: string, outflow: string, net: string, cumulative: string}>
     */
    private function cashFlow(Wedding $wedding): array
    {
        $zero = 'CAST(0 AS DECIMAL(20,2))';

        $inflows = $wedding->revenues()->toBase()
            ->where('status', RevenueStatus::Received->value)
            ->selectRaw("DATE_FORMAT(received_date, '%Y-%m') AS month, base_amount AS inflow, {$zero} AS outflow");

        $outflows = ExpensePayment::query()->toBase()
            ->join('expenses', 'expenses.id', '=', 'expense_payments.expense_id')
            ->where('expenses.wedding_id', $wedding->getKey())
            ->whereNull('expenses.deleted_at')
            ->selectRaw("DATE_FORMAT(expense_payments.paid_date, '%Y-%m') AS month, {$zero} AS inflow, expense_payments.base_amount AS outflow");

        $months = DB::query()
            ->fromSub($inflows->unionAll($outflows), 'flows')
            ->select('month')
            ->selectRaw('SUM(inflow) AS inflow, SUM(outflow) AS outflow')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        if ($months->isEmpty()) {
            return [];
        }

        $flow = [];
        $cumulative = new Number('0.00');
        $last = CarbonImmutable::createFromFormat('!Y-m', $months->keys()->last());

        for ($month = CarbonImmutable::createFromFormat('!Y-m', $months->keys()->first()); $month <= $last; $month = $month->addMonthNoOverflow()) {
            $row = $months->get($month->format('Y-m'));
            $inflow = new Number($row->inflow ?? '0.00');
            $outflow = new Number($row->outflow ?? '0.00');
            $cumulative += $inflow - $outflow;

            $flow[] = [
                'month' => $month->format('Y-m'),
                'inflow' => (string) $inflow,
                'outflow' => (string) $outflow,
                'net' => (string) ($inflow - $outflow),
                'cumulative' => (string) $cumulative,
            ];
        }

        return $flow;
    }

    /**
     * Unpaid balances due from today to today + UPCOMING_DAYS, soonest first. Cancelled
     * expenses owe nothing more; the outstanding amount itself comes from
     * Expense::outstanding_amount, in the expense currency.
     *
     * @return Collection<int, Expense>
     */
    private function upcomingPayments(Wedding $wedding, CarbonImmutable $today): Collection
    {
        return $wedding->expenses()
            ->join('categories', 'categories.id', '=', 'expenses.category_id')
            ->select('expenses.*', 'categories.name AS category_name')
            ->where('expenses.status', '<>', ExpenseStatus::Cancelled->value)
            ->whereBetween('expenses.due_date', [$today->toDateString(), $today->addDays(self::UPCOMING_DAYS)->toDateString()])
            ->whereRaw('COALESCE(expenses.actual_amount, expenses.estimated_amount) > expenses.paid_amount')
            ->orderBy('expenses.due_date')
            ->orderBy('expenses.id')
            ->get();
    }

    /**
     * BCMath sum of one column of aggregated rows; NULL (nothing to sum) counts as zero.
     *
     * @param  Collection<int, object>  $rows
     */
    private function sum(Collection $rows, string $column): string
    {
        return (string) $rows->reduce(
            fn (Number $total, object $row): Number => $total + new Number($row->{$column} ?? '0.00'),
            new Number('0.00'),
        );
    }
}
