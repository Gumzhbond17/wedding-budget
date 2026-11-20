<?php

namespace App\Http\Resources;

use App\Enums\Currency;
use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\Expense;
use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array from DashboardSummaryService::summarize(). Every amount carries its currency:
 * base totals are in the wedding's base currency, per-currency and upcoming amounts in their own.
 *
 * @property array<string, mixed> $resource
 */
class DashboardResource extends JsonResource
{
    use FormatsMoney;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = $this->resource['base_currency'];
        $summary = $this->resource['summary'];
        $money = fn (string $amount): array => $this->money($amount, $base);

        return [
            'as_of' => $this->resource['as_of'],
            'timezone' => DashboardSummaryService::TIMEZONE,
            'base_currency' => $base->value,
            'summary' => [
                'budget_target' => $money($summary['budget_target']),
                'revenue' => array_map($money, $summary['revenue']),
                'expenses' => array_map($money, $summary['expenses']),
                'remaining_budget' => $money($summary['remaining_budget']),
                'net_cash_position' => $money($summary['net_cash_position']),
                'funding_gap' => $money($summary['funding_gap']),
                'secured_gap' => $money($summary['secured_gap']),
                'planned_total' => $money($summary['planned_total']),
                'unallocated_budget' => $money($summary['unallocated_budget']),
            ],
            'per_currency' => array_map(fn (array $row): array => [
                'currency' => $row['currency']->value,
                'revenue' => array_map(fn (array $pair): array => $this->pair($pair, $row['currency'], $base), $row['revenue']),
                'expenses' => array_map(fn (array $pair): array => $this->pair($pair, $row['currency'], $base), $row['expenses']),
            ], $this->resource['per_currency']),
            'categories' => array_map(fn (array $category): array => [
                'id' => $category['id'],
                'name' => $category['name'],
                'planned' => $category['planned'] === null ? null : $money($category['planned']),
                'estimated' => $money($category['estimated']),
                'actual' => $money($category['actual']),
                'paid' => $money($category['paid']),
                'committed' => $money($category['committed']),
                'used_percent' => $category['used_percent'],
                'health' => $category['health']?->value,
            ], $this->resource['categories']),
            'expense_breakdown' => array_map(fn (array $slice): array => [
                'category_id' => $slice['category_id'],
                'name' => $slice['name'],
                'committed' => $money($slice['committed']),
                'share_percent' => $slice['share_percent'],
            ], $this->resource['expense_breakdown']),
            'unplanned_spending' => array_map(fn (array $category): array => [
                'category_id' => $category['id'],
                'name' => $category['name'],
                'committed' => $money($category['committed']),
            ], $this->resource['unplanned_spending']),
            'cash_flow' => array_map(fn (array $month): array => [
                'month' => $month['month'],
                'inflow' => $money($month['inflow']),
                'outflow' => $money($month['outflow']),
                'net' => $money($month['net']),
                'cumulative' => $money($month['cumulative']),
            ], $this->resource['cash_flow']),
            'upcoming_payments' => $this->resource['upcoming_payments']->map(fn (Expense $expense): array => [
                'expense_id' => $expense->id,
                'description' => $expense->description,
                'vendor_name' => $expense->vendor_name,
                'category' => ['id' => $expense->category_id, 'name' => $expense->category_name],
                'status' => $expense->status,
                'due_date' => $expense->due_date->toDateString(),
                // Expense currency; "estimated" until the invoice (actual_amount) is known.
                'outstanding_amount' => [
                    ...$this->money($expense->outstanding_amount, $expense->currency),
                    'basis' => $expense->actual_amount === null ? 'estimated' : 'actual',
                ],
            ])->all(),
        ];
    }

    /**
     * @param  array{amount: string, base: string}  $pair
     * @return array{amount: array{amount: string, currency: string}, base: array{amount: string, currency: string}}
     */
    private function pair(array $pair, Currency $currency, Currency $base): array
    {
        return ['amount' => $this->money($pair['amount'], $currency), 'base' => $this->money($pair['base'], $base)];
    }
}
