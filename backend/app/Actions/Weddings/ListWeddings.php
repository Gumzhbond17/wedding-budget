<?php

namespace App\Actions\Weddings;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The user's own weddings, next wedding date first. Scoped through the relation, so another
 * user's weddings can never appear.
 */
final class ListWeddings
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Wedding>
     */
    public function handle(User $user): LengthAwarePaginator
    {
        return $user->weddings()
            ->orderByDesc('wedding_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }
}
