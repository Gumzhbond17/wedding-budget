<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Auth\Access\Response;

/**
 * Every domain row is owned through its wedding. A failed check answers 404, never 403,
 * so a guessed id is indistinguishable from one that does not exist (no IDOR oracle).
 */
trait ChecksWeddingOwnership
{
    protected function ownsWedding(User $user, Wedding $wedding): Response
    {
        return (int) $wedding->user_id === (int) $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
