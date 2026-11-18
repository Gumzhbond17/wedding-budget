<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\Collection;

/**
 * An expense's payments in the order they were made. Not paginated: payments per expense are
 * few (deposit, instalments, balance).
 */
final class ListExpensePayments
{
    /**
     * @return Collection<int, ExpensePayment>
     */
    public function handle(Expense $expense): Collection
    {
        return $expense->payments()->orderBy('paid_date')->orderBy('id')->get();
    }
}
