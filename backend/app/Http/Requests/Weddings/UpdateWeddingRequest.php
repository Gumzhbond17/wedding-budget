<?php

namespace App\Http\Requests\Weddings;

use App\Enums\Currency;
use App\Enums\WeddingStatus;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update. The base_currency lock (no records, no rates) is enforced by UpdateWedding
 * under a row lock, where it cannot race with concurrent record writes.
 */
class UpdateWeddingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'wedding_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(WeddingStatus::class)],
            'base_currency' => ['sometimes', Rule::enum(Currency::class)],
            'budget_target' => ['sometimes', new Money],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
