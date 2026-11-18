<?php

namespace App\Http\Requests\Payments;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpensePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', Money::positive()],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'paid_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_method' => ['sometimes', 'nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
