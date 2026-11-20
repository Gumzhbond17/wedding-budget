<?php

namespace App\Http\Requests\Expenses;

use App\Actions\Expenses\ManageExpense;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rate, base amounts and paid totals are never accepted from the client. Only user-settable
 * statuses are allowed; partially_paid and paid follow from payments.
 */
class StoreExpenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'estimated_amount' => ['required', new Money],
            'actual_amount' => ['nullable', new Money],
            'status' => ['sometimes', Rule::in(array_column(ExpenseStatus::userSettable(), 'value'))],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ManageExpense::messages();
    }
}
