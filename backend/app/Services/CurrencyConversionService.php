<?php

namespace App\Services;

use App\Contracts\ExchangeRateProvider;
use App\Enums\Currency;
use App\Models\Wedding;
use BcMath\Number;
use Carbon\CarbonInterface;
use RoundingMode;

/**
 * Converts record amounts into the wedding's base currency.
 *
 * Rounding rule, the only one in the application:
 * - base amount = amount * rate, multiplied at full precision with BCMath (never floats);
 * - the product is rounded once, half away from zero, to 2 decimals for DECIMAL(20,2) storage
 *   (e.g. 0.005 -> 0.01; 21,850.125 -> 21,850.13);
 * - rates are used exactly as stored, DECIMAL(20,8), and never re-rounded;
 * - every stored amount is converted on its own; totals are SUMs of stored base amounts and
 *   are never converted again, so a total always equals the sum of its visible lines.
 * Display rounding (0 decimals for LAK) is a frontend concern and never written back.
 */
final class CurrencyConversionService
{
    public const int STORAGE_SCALE = 2;

    public function __construct(private readonly ExchangeRateProvider $rates) {}

    /**
     * Rate from $currency to the wedding's base currency on $date (1 for the base currency).
     */
    public function rateToBase(Wedding $wedding, Currency $currency, CarbonInterface $date): Number
    {
        return $this->rates->rate($wedding, $currency, $wedding->base_currency, $date);
    }

    /**
     * Apply a snapshotted rate to an amount, following the rounding rule above.
     */
    public function convert(string $amount, Number|string $rate): string
    {
        $rate = $rate instanceof Number ? $rate : new Number($rate);

        return (string) (new Number($amount) * $rate)->round(self::STORAGE_SCALE, RoundingMode::HalfAwayFromZero);
    }
}
