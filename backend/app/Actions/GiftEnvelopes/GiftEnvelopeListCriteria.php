<?php

namespace App\Actions\GiftEnvelopes;

/**
 * Validated filters, sort and page size for the gift envelope list, built by
 * ListGiftEnvelopesRequest::criteria() from whitelisted input only.
 */
final readonly class GiftEnvelopeListCriteria
{
    /**
     * @param  'asc'|'desc'  $sortDirection
     */
    public function __construct(
        public ?string $search,
        public ?string $guestGroup,
        public ?string $dateFrom,
        public ?string $dateTo,
        public string $sortColumn,
        public string $sortDirection,
        public int $perPage,
    ) {}
}
