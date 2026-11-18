<?php

namespace Database\Factories\Concerns;

use App\Services\CurrencyConversionService;

/**
 * Lets factories produce rows whose snapshot columns are consistent with their amounts,
 * using the application's single rounding rule (see CurrencyConversionService::convert()).
 */
trait ConvertsToBaseAmount
{
    protected function toBaseAmount(?string $amount, string $exchangeRate): ?string
    {
        if ($amount === null) {
            return null;
        }

        return app(CurrencyConversionService::class)->convert($amount, $exchangeRate);
    }

    /**
     * Whole amount in major units, e.g. 1_500_000 LAK or 850 USD, as a DECIMAL(20,2) string.
     */
    protected function money(int $min, int $max, int $step = 1): string
    {
        return fake()->numberBetween(intdiv($min, $step), intdiv($max, $step)) * $step.'.00';
    }
}
