<?php

namespace App\Policies;

use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends WeddingScopedPolicy<Revenue>
 */
class RevenuePolicy extends WeddingScopedPolicy
{
    /**
     * @param  Revenue  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->wedding;
    }
}
