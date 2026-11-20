<?php

namespace App\Http\Requests\GiftEnvelopes;

use App\Actions\GiftEnvelopes\GiftEnvelopeListCriteria;
use App\Enums\GuestGroup;
use App\Http\Requests\ListRecordsRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ?search=&guest_group=&date_from=&date_to=&sort=-received_date&per_page=25
 *
 * Sort keys are whitelisted, so user input never reaches ORDER BY unchecked.
 */
class ListGiftEnvelopesRequest extends FormRequest
{
    /** @var list<string> */
    private const array SORTABLE = ['full_name', 'amount', 'received_date', 'created_at'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sorts = collect(self::SORTABLE)->flatMap(fn (string $column): array => [$column, "-{$column}"])->all();

        return [
            'search' => ['sometimes', 'string', 'max:150'],
            'guest_group' => ['sometimes', Rule::enum(GuestGroup::class)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['sometimes', Rule::in($sorts)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListRecordsRequest::MAX_PER_PAGE],
        ];
    }

    public function criteria(string $defaultSort): GiftEnvelopeListCriteria
    {
        $sort = $this->validated('sort', $defaultSort);
        $search = trim((string) $this->validated('search', ''));

        return new GiftEnvelopeListCriteria(
            search: $search === '' ? null : $search,
            guestGroup: $this->validated('guest_group'),
            dateFrom: $this->validated('date_from'),
            dateTo: $this->validated('date_to'),
            sortColumn: ltrim($sort, '-'),
            sortDirection: str_starts_with($sort, '-') ? 'desc' : 'asc',
            perPage: (int) $this->validated('per_page', ListRecordsRequest::DEFAULT_PER_PAGE),
        );
    }
}
