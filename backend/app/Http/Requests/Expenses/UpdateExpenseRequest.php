<?php

namespace App\Http\Requests\Expenses;

use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'vendor_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'estimated_amount' => ['sometimes', new Money],
            'actual_amount' => ['sometimes', 'nullable', new Money],
            'status' => ['sometimes', Rule::in(array_column(ExpenseStatus::userSettable(), 'value'))],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
