<?php

namespace App\Http\Requests\GiftEnvelopes;

use App\Enums\GuestGroup;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * No currency field: an envelope is always in the wedding's base currency.
 */
class StoreGiftEnvelopeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'amount' => ['required', Money::positive()],
            'guest_group' => ['required', Rule::enum(GuestGroup::class)],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
