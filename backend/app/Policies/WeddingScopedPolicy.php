<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wedding;
use App\Policies\Concerns\ChecksWeddingOwnership;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for records that live inside a wedding. Listing and creating are authorized against
 * the wedding from the route; single records against the wedding they belong to.
 *
 * Scoped route bindings already guarantee a nested record belongs to the wedding in the URL;
 * this check is the second, independent layer (the wedding itself must be the user's).
 *
 * @template TModel of Model
 */
abstract class WeddingScopedPolicy
{
    use ChecksWeddingOwnership;

    /**
     * @param  TModel  $model
     */
    abstract protected function weddingOf(Model $model): Wedding;

    public function viewAny(User $user, Wedding $wedding): Response
    {
        return $this->ownsWedding($user, $wedding);
    }

    public function create(User $user, Wedding $wedding): Response
    {
        return $this->ownsWedding($user, $wedding);
    }

    /**
     * @param  TModel  $model
     */
    public function view(User $user, Model $model): Response
    {
        return $this->ownsWedding($user, $this->weddingOf($model));
    }

    /**
     * @param  TModel  $model
     */
    public function update(User $user, Model $model): Response
    {
        return $this->ownsWedding($user, $this->weddingOf($model));
    }

    /**
     * @param  TModel  $model
     */
    public function delete(User $user, Model $model): Response
    {
        return $this->ownsWedding($user, $this->weddingOf($model));
    }
}
