<?php

namespace App\Http\Requests\Revenues;

use App\Actions\Revenues\ManageRevenue;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\RevenueStatus;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * exchange_rate and base_amount are deliberately absent: validated() drops them, and the
 * backend computes both. Category ownership and type are checked in ManageRevenue.
 */
class StoreRevenueRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'],
            'source' => ['required', 'string', 'max:150'],
            'amount' => ['required', Money::positive()],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'status' => ['sometimes', Rule::enum(RevenueStatus::class)],
            'expected_date' => ['nullable', 'date_format:Y-m-d'],
            'received_date' => ['nullable', 'date_format:Y-m-d', 'required_if:status,received'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ManageRevenue::messages();
    }
}
