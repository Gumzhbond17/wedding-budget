<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wedding;
use App\Policies\Concerns\ChecksWeddingOwnership;
use Illuminate\Auth\Access\Response;

class WeddingPolicy
{
    use ChecksWeddingOwnership;

    /**
     * Listing is always scoped to the user's own weddings by the controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Wedding $wedding): Response
    {
        return $this->ownsWedding($user, $wedding);
    }

    public function update(User $user, Wedding $wedding): Response
    {
        return $this->ownsWedding($user, $wedding);
    }

    public function delete(User $user, Wedding $wedding): Response
    {
        return $this->ownsWedding($user, $wedding);
    }
}
