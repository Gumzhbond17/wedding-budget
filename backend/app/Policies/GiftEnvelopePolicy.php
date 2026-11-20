<?php

namespace App\Policies;

use App\Models\GiftEnvelope;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends WeddingScopedPolicy<GiftEnvelope>
 */
class GiftEnvelopePolicy extends WeddingScopedPolicy
{
    /**
     * @param  GiftEnvelope  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->wedding;
    }
}
