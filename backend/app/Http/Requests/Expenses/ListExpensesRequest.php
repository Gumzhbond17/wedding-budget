<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseStatus;
use App\Http\Requests\ListRecordsRequest;

class ListExpensesRequest extends ListRecordsRequest
{
    protected function sortable(): array
    {
        return ['base_estimated_amount', 'base_actual_amount', 'due_date', 'created_at'];
    }

    protected function statuses(): array
    {
        return array_column(ExpenseStatus::cases(), 'value');
    }
}
