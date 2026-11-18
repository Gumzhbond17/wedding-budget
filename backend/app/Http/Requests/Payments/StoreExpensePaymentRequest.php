<?php

namespace App\Http\Requests\Payments;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * currency is optional; when sent it must equal the expense currency (checked by
 * ManageExpensePayment, backed by a composite FK). The rate snapshot is computed server-side.
 */
class StoreExpensePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', Money::positive()],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
