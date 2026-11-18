<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared query-string contract for paginated revenue and expense lists:
 * ?category_id=&status=&currency=&date_from=&date_to=&sort=-due_date&per_page=25
 *
 * Sort keys are whitelisted per list, so user input never reaches ORDER BY unchecked.
 */
abstract class ListRecordsRequest extends FormRequest
{
    public const int MAX_PER_PAGE = 100;

    /**
     * @return list<string> sortable columns
     */
    abstract protected function sortable(): array;

    /**
     * @return list<string> accepted status values
     */
    abstract protected function statuses(): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sorts = collect($this->sortable())->flatMap(fn (string $column): array => [$column, "-{$column}"])->all();

        return [
            'category_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in($this->statuses())],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['sometimes', Rule::in($sorts)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sortColumnAndDirection(string $default): array
    {
        $sort = $this->validated('sort', $default);

        return str_starts_with($sort, '-') ? [substr($sort, 1), 'desc'] : [$sort, 'asc'];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }
}
