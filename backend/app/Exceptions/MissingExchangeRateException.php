<?php

namespace App\Exceptions;

use App\Enums\Currency;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * No rate entered for the pair on or before the transaction date.
 *
 * A validation error (422, standard {message, errors} body) rather than a server error:
 * the user fixes it by adding a rate in Settings. A later rate is never used as a fallback,
 * because that would snapshot a rate that did not exist on the transaction date.
 */
final class MissingExchangeRateException extends ValidationException
{
    public static function forPair(Currency $from, Currency $to, CarbonInterface $date): self
    {
        return self::withMessages([
            'currency' => sprintf(
                'No %s to %s exchange rate is set on or before %s. Add one in Settings first.',
                $from->value,
                $to->value,
                $date->toDateString(),
            ),
        ]);
    }
}
