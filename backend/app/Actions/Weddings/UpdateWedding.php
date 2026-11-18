<?php

namespace App\Actions\Weddings;

use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Updates a wedding. base_currency may only change while nothing depends on it
 * (docs/business-rules.md): every base_* snapshot and every exchange rate is expressed in it.
 */
final class UpdateWedding
{
    /**
     * @param  array{name?: string, wedding_date?: ?string, status?: string, base_currency?: string, budget_target?: string, notes?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(Wedding $wedding, array $data): Wedding
    {
        return DB::transaction(function () use ($wedding, $data): Wedding {
            // Exclusive lock: record and rate writes take a shared lock on this row, so they
            // cannot slip in between the dependency check and the currency change.
            $wedding = Wedding::query()->lockForUpdate()->findOrFail($wedding->getKey());
            $wedding->fill($data);

            if ($wedding->isDirty('base_currency')) {
                $this->ensureBaseCurrencyCanChange($wedding);
            }

            $wedding->save();

            return $wedding;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureBaseCurrencyCanChange(Wedding $wedding): void
    {
        if ($wedding->hasFinancialRecords()) {
            throw ValidationException::withMessages([
                'base_currency' => __('The base currency cannot change once revenues or expenses exist (including deleted ones).'),
            ]);
        }

        if ($wedding->exchangeRates()->exists()) {
            throw ValidationException::withMessages([
                'base_currency' => __('Delete the exchange rates first: they convert into the current base currency.'),
            ]);
        }
    }
}
