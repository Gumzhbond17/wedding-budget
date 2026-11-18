<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryType;
use App\Models\Wedding;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Wedding $wedding */
        $wedding = $this->route('wedding');

        return [
            'type' => ['required', Rule::enum(CategoryType::class)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories')->where('wedding_id', $wedding->getKey())->where('type', $this->input('type')),
            ],
            // Budget per category exists for expense categories only (a CHECK constraint backs this).
            'planned_amount' => ['nullable', 'prohibited_unless:type,expense', new Money],
        ];
    }
}
