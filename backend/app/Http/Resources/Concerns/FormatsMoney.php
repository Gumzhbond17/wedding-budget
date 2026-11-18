<?php

namespace App\Http\Resources\Concerns;

use App\Enums\Currency;

/**
 * Every amount leaves the API as {"amount": "1500.00", "currency": "USD"}: a string, so
 * JavaScript never rounds large LAK values through a float, and always with its currency,
 * so original and base-currency figures cannot be confused.
 */
trait FormatsMoney
{
    /**
     * @return array{amount: string, currency: string}|null
     */
    protected function money(?string $amount, Currency $currency): ?array
    {
        return $amount === null ? null : ['amount' => $amount, 'currency' => $currency->value];
    }
}
