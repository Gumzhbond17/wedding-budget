<?php

namespace App\Actions\ExchangeRates;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates, updates and deletes a wedding's manual exchange rates.
 *
 * Rates are stored foreign -> base only (docs/business-rules.md), so to_currency is always the
 * wedding's base currency: it may be omitted, and any other value is rejected. The rules are
 * enforced here, not only in the HTTP layer, so seeders and a future API-backed provider cannot
 * store a rate the lookup would never use.
 *
 * Changing or deleting a rate never touches existing records: they hold their own snapshot.
 */
final class ManageExchangeRate
{
    /**
     * Validation rules, shared with the HTTP Form Requests.
     *
     * @return array<string, mixed>
     */
    public static function rules(Wedding $wedding, mixed $effectiveDate, ?ExchangeRate $ignore = null): array
    {
        $base = $wedding->base_currency;

        $fromCurrency = ['required', Rule::enum(Currency::class)->except([$base])];

        // One rate per pair per day (ignoring the row being edited). Only checked for a well-formed
        // date; a malformed one already fails date_format below.
        if (is_string($effectiveDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate) === 1) {
            $fromCurrency[] = Rule::unique('exchange_rates', 'from_currency')
                ->where('wedding_id', $wedding->getKey())
                ->where('to_currency', $base->value)
                ->where('effective_date', $effectiveDate)
                ->ignore($ignore);
        }

        return [
            'from_currency' => $fromCurrency,
            'to_currency' => ['sometimes', Rule::in([$base->value])],
            // The pattern mirrors DECIMAL(20,8) exactly (12 integer digits, up to 8 decimals) without
            // a float round-trip. "numeric" makes gt:0 compare values, not string lengths.
            'rate' => ['required', 'numeric', 'regex:/^\d{1,12}(\.\d{1,8})?$/', 'gt:0'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @param  array{from_currency: string, to_currency?: string, rate: string, effective_date: string}  $data
     *
     * @throws ValidationException
     */
    public function create(Wedding $wedding, array $data): ExchangeRate
    {
        return DB::transaction(function () use ($wedding, $data): ExchangeRate {
            $wedding = $this->lockWedding($wedding->getKey());

            $rate = $wedding->exchangeRates()->make($this->validate($wedding, $data));
            $rate->to_currency = $wedding->base_currency;
            $rate->save();

            return $rate;
        });
    }

    /**
     * @param  array{from_currency?: string, to_currency?: string, rate?: string, effective_date?: string}  $data
     *
     * @throws ValidationException
     */
    public function update(ExchangeRate $rate, array $data): ExchangeRate
    {
        return DB::transaction(function () use ($rate, $data): ExchangeRate {
            $wedding = $this->lockWedding($rate->wedding_id);
            $rate = ExchangeRate::query()->lockForUpdate()->findOrFail($rate->getKey());

            // Partial updates are validated as a whole against the stored values.
            $merged = [
                'from_currency' => $rate->from_currency->value,
                'rate' => $rate->rate,
                'effective_date' => $rate->effective_date->toDateString(),
                ...$data,
            ];

            $rate->fill($this->validate($wedding, $merged, $rate));
            $rate->to_currency = $wedding->base_currency;
            $rate->save();

            return $rate;
        });
    }

    public function delete(ExchangeRate $rate): void
    {
        $rate->delete();
    }

    /**
     * Shared lock: base_currency cannot change (UpdateWedding, exclusive lock) while a rate
     * that converts into it is being written.
     */
    private function lockWedding(int $weddingId): Wedding
    {
        return Wedding::query()->sharedLock()->findOrFail($weddingId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Wedding $wedding, array $data, ?ExchangeRate $ignore = null): array
    {
        $validator = Validator::make($data, self::rules($wedding, $data['effective_date'] ?? null, $ignore), self::messages($wedding));

        return collect($validator->validate())->except('to_currency')->all();
    }

    /**
     * Messages for rules(), shared with the Form Requests so the first check a request meets
     * already speaks plainly (not "The from currency has already been taken.").
     *
     * @return array<string, string>
     */
    public static function messages(Wedding $wedding): array
    {
        return [
            'from_currency.enum' => __('Rates are entered from a foreign currency into the base currency (:currency).', ['currency' => $wedding->base_currency->value]),
            'from_currency.unique' => __('A rate for this currency already exists on that date.'),
            'to_currency.in' => __("Rates must convert into the wedding's base currency (:currency).", ['currency' => $wedding->base_currency->value]),
            'rate.regex' => __('The rate must be a number with at most 12 digits before and 8 after the decimal point.'),
        ];
    }
}
