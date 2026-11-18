<?php

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends WeddingScopedPolicy<ExchangeRate>
 */
class ExchangeRatePolicy extends WeddingScopedPolicy
{
    /**
     * @param  ExchangeRate  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->wedding;
    }
}
