<?php

namespace App\Http\Requests\ExchangeRates;

use App\Actions\ExchangeRates\ManageExchangeRate;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update: every field is optional; the action validates the merged result again.
 */
class UpdateExchangeRateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Wedding $wedding */
        $wedding = $this->route('wedding');
        /** @var ExchangeRate $rate */
        $rate = $this->route('exchangeRate');

        $effectiveDate = $this->input('effective_date', $rate->effective_date->toDateString());

        return collect(ManageExchangeRate::rules($wedding, $effectiveDate, $rate))
            ->map(fn (array $rules): array => ['sometimes', ...array_filter($rules, fn (mixed $rule): bool => $rule !== 'required')])
            ->all();
    }
}
