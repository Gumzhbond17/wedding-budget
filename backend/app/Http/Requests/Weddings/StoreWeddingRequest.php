<?php

namespace App\Http\Requests\Weddings;

use App\Enums\Currency;
use App\Enums\WeddingStatus;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeddingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'wedding_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(WeddingStatus::class)],
            'base_currency' => ['sometimes', Rule::enum(Currency::class)],
            'budget_target' => ['sometimes', new Money],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
