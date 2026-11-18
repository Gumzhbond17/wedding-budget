<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends WeddingScopedPolicy<Expense>
 */
class ExpensePolicy extends WeddingScopedPolicy
{
    /**
     * @param  Expense  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->wedding;
    }
}
