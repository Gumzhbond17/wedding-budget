<?php

namespace App\Actions;

/**
 * Validated filters, sort and page size for a paginated revenue or expense list.
 *
 * Built by ListRecordsRequest::criteria() from whitelisted input only, so the query classes
 * never see raw request data and can be reused outside HTTP (exports, console commands).
 */
final readonly class RecordListCriteria
{
    /**
     * @param  'asc'|'desc'  $sortDirection
     */
    public function __construct(
        public ?int $categoryId,
        public ?string $status,
        public ?string $currency,
        public ?string $dateFrom,
        public ?string $dateTo,
        public string $sortColumn,
        public string $sortDirection,
        public int $perPage,
    ) {}
}
