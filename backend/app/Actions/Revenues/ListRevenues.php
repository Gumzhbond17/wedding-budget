<?php

namespace App\Actions\Revenues;

use App\Actions\RecordListCriteria;
use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated revenue list of one wedding. The date range filters on the transaction date:
 * received_date, else expected_date.
 */
final class ListRevenues
{
    /**
     * @return LengthAwarePaginator<int, Revenue>
     */
    public function handle(Wedding $wedding, RecordListCriteria $criteria): LengthAwarePaginator
    {
        return $wedding->revenues()
            ->with('category')
            ->when($criteria->categoryId, fn ($query, int $id) => $query->where('category_id', $id))
            ->when($criteria->status, fn ($query, string $status) => $query->where('status', $status))
            ->when($criteria->currency, fn ($query, string $currency) => $query->where('currency', $currency))
            ->when($criteria->dateFrom, fn ($query, string $from) => $query->whereRaw('COALESCE(received_date, expected_date) >= ?', [$from]))
            ->when($criteria->dateTo, fn ($query, string $to) => $query->whereRaw('COALESCE(received_date, expected_date) <= ?', [$to]))
            ->orderBy($criteria->sortColumn, $criteria->sortDirection)
            // Tie-breaker: equal sort values would otherwise shuffle rows between pages.
            ->orderBy('id', $criteria->sortDirection)
            ->paginate($criteria->perPage)
            ->withQueryString();
    }
}
