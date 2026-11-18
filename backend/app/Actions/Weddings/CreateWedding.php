<?php

namespace App\Actions\Weddings;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;

/**
 * Creates a wedding for its owner together with its own copy of the default categories.
 * One transaction, so a wedding never exists without its categories.
 */
final class CreateWedding
{
    /**
     * @param  array{name: string, wedding_date?: ?string, base_currency?: string, budget_target?: string, notes?: ?string}  $attributes
     */
    public function handle(User $user, array $attributes): Wedding
    {
        return DB::transaction(function () use ($user, $attributes): Wedding {
            $wedding = $user->weddings()->create($attributes);
            $wedding->createDefaultCategories();

            return $wedding;
        });
    }
}
