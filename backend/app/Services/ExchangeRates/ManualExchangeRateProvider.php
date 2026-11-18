<?php

namespace App\Services\ExchangeRates;

use App\Contracts\ExchangeRateProvider;
use App\Enums\Currency;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Wedding;
use BcMath\Number;
use Carbon\CarbonInterface;

/**
 * Rates entered manually per wedding. Picks the latest rate with effective_date on or
 * before the requested date, so back-dated records use the rate of their own day.
 */
final class ManualExchangeRateProvider implements ExchangeRateProvider
{
    public function rate(Wedding $wedding, Currency $from, Currency $to, CarbonInterface $date): Number
    {
        if ($from === $to) {
            return new Number('1');
        }

        // Served by the unique (wedding_id, from_currency, to_currency, effective_date) index.
        $rate = $wedding->exchangeRates()
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($rate === null) {
            throw new MissingExchangeRateException($from, $to, $date);
        }

        return new Number($rate);
    }
}
