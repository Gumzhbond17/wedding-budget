<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Rules\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The type is fixed after creation: records rely on it (revenue vs expense).
 */
class UpdateCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'type' => ['prohibited'],
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('categories')
                    ->where('wedding_id', $category->wedding_id)
                    ->where('type', $category->type->value)
                    ->ignore($category),
            ],
            'planned_amount' => [
                'sometimes', 'nullable', new Money,
                Rule::prohibitedIf($category->type === CategoryType::Revenue),
            ],
        ];
    }
}
