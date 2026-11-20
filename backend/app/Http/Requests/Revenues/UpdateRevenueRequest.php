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
 * Partial update. "received needs received_date" is checked by ManageRevenue against the
 * merged record, because either field may come from the stored row.
 */
class UpdateRevenueRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer'],
            'source' => ['sometimes', 'required', 'string', 'max:150'],
            'amount' => ['sometimes', Money::positive()],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'status' => ['sometimes', Rule::enum(RevenueStatus::class)],
            'expected_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'received_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'payment_method' => ['sometimes', 'nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
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
