<?php

namespace App\Contracts;

use App\Enums\Currency;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Wedding;
use App\Services\ExchangeRates\ManualExchangeRateProvider;
use BcMath\Number;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Bind;

/**
 * Source of exchange rates. The MVP reads rates the user entered in Settings; an API-backed
 * provider can replace it later by changing this binding, without touching any caller.
 */
#[Bind(ManualExchangeRateProvider::class)]
interface ExchangeRateProvider
{
    /**
     * Rate valid on $date such that 1 $from = rate $to.
     *
     * @throws MissingExchangeRateException
     */
    public function rate(Wedding $wedding, Currency $from, Currency $to, CarbonInterface $date): Number;
}
