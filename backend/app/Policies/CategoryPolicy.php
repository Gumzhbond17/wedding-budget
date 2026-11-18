<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends WeddingScopedPolicy<Category>
 */
class CategoryPolicy extends WeddingScopedPolicy
{
    /**
     * @param  Category  $model
     */
    protected function weddingOf(Model $model): Wedding
    {
        return $model->wedding;
    }
}
