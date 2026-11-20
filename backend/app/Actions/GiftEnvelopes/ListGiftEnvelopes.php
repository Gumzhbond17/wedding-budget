<?php

namespace App\Actions\GiftEnvelopes;

use App\Models\GiftEnvelope;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated gift envelope list of one wedding. Search matches any part of the full name,
 * so "Bounmy" finds "Mr Bounmy Sengdara"; LIKE wildcards in the input are matched literally.
 */
final class ListGiftEnvelopes
{
    /**
     * @return LengthAwarePaginator<int, GiftEnvelope>
     */
    public function handle(Wedding $wedding, GiftEnvelopeListCriteria $criteria): LengthAwarePaginator
    {
        return $wedding->giftEnvelopes()
            ->when($criteria->search, fn ($query, string $search) => $query->whereLike('full_name', '%'.$this->escapeLike($search).'%'))
            ->when($criteria->guestGroup, fn ($query, string $group) => $query->where('guest_group', $group))
            ->when($criteria->dateFrom, fn ($query, string $from) => $query->where('received_date', '>=', $from))
            ->when($criteria->dateTo, fn ($query, string $to) => $query->where('received_date', '<=', $to))
            ->orderBy($criteria->sortColumn, $criteria->sortDirection)
            // Tie-breaker: equal sort values would otherwise shuffle rows between pages.
            ->orderBy('id', $criteria->sortDirection)
            ->paginate($criteria->perPage)
            ->withQueryString();
    }

    /**
     * MySQL's default LIKE escape character is the backslash.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
