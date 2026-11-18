<?php

namespace App\Exceptions;

use App\Enums\Currency;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * No rate entered for the pair on or before the record's date. The user must add one in
 * Settings; guessing a rate would silently corrupt the snapshot.
 */
final class MissingExchangeRateException extends RuntimeException
{
    public function __construct(
        public readonly Currency $from,
        public readonly Currency $to,
        public readonly CarbonInterface $date,
    ) {
        parent::__construct(sprintf(
            'No %s to %s exchange rate is set on or before %s. Add one in Settings first.',
            $from->value,
            $to->value,
            $date->toDateString(),
        ));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'exchange_rate_missing',
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
