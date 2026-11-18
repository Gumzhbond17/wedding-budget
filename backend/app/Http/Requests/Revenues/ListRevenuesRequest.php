<?php

namespace App\Http\Requests\Revenues;

use App\Enums\RevenueStatus;
use App\Http\Requests\ListRecordsRequest;

class ListRevenuesRequest extends ListRecordsRequest
{
    protected function sortable(): array
    {
        return ['base_amount', 'expected_date', 'received_date', 'created_at'];
    }

    protected function statuses(): array
    {
        return array_column(RevenueStatus::cases(), 'value');
    }
}
