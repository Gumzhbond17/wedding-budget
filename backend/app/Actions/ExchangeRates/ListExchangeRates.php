<?php

namespace App\Actions\ExchangeRates;

use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A wedding's manual exchange rates, newest effective date first.
 */
final class ListExchangeRates
{
    public const int PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, ExchangeRate>
     */
    public function handle(Wedding $wedding): LengthAwarePaginator
    {
        return $wedding->exchangeRates()
            ->orderByDesc('effective_date')
            ->orderBy('from_currency')
            ->paginate(self::PER_PAGE);
    }
}
