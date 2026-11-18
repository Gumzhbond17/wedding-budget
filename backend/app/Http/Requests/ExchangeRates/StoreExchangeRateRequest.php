<?php

namespace App\Http\Requests\ExchangeRates;

use App\Actions\ExchangeRates\ManageExchangeRate;
use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same rules as the action (single source); the action re-checks them under a lock.
 */
class StoreExchangeRateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Wedding $wedding */
        $wedding = $this->route('wedding');

        return ManageExchangeRate::rules($wedding, $this->input('effective_date'));
    }
}
