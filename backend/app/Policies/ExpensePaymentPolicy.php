<?php

namespace App\Policies;

use App\Models\ExpensePayment;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * Payments are nested one level deeper: wedding -> expense -> payment.
 *
 * @extends WeddingScopedPolicy<ExpensePayment>
 */
class ExpensePaymentPolicy extends WeddingScopedPolicy
{
    /**
     * @param  ExpensePayment  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->expense->wedding;
    }
}
