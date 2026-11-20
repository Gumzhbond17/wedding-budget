<?php

namespace App\Http\Requests\GiftEnvelopes;

use App\Enums\GuestGroup;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update; a field that is sent must still be valid on its own.
 */
class UpdateGiftEnvelopeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'amount' => ['sometimes', Money::positive()],
            'guest_group' => ['sometimes', Rule::enum(GuestGroup::class)],
            'received_date' => ['sometimes', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
