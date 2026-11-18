<?php

namespace App\Actions\Expenses;

use App\Actions\RecordListCriteria;
use App\Models\Expense;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated expense list of one wedding. The date range filters on due_date.
 */
final class ListExpenses
{
    /**
     * @return LengthAwarePaginator<int, Expense>
     */
    public function handle(Wedding $wedding, RecordListCriteria $criteria): LengthAwarePaginator
    {
        return $wedding->expenses()
            ->with('category')
            ->when($criteria->categoryId, fn ($query, int $id) => $query->where('category_id', $id))
            ->when($criteria->status, fn ($query, string $status) => $query->where('status', $status))
            ->when($criteria->currency, fn ($query, string $currency) => $query->where('currency', $currency))
            ->when($criteria->dateFrom, fn ($query, string $from) => $query->where('due_date', '>=', $from))
            ->when($criteria->dateTo, fn ($query, string $to) => $query->where('due_date', '<=', $to))
            ->orderBy($criteria->sortColumn, $criteria->sortDirection)
            // Tie-breaker: equal sort values would otherwise shuffle rows between pages.
            ->orderBy('id', $criteria->sortDirection)
            ->paginate($criteria->perPage)
            ->withQueryString();
    }
}
