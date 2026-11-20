<?php

namespace App\Actions\Categories;

use App\Enums\CategoryType;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

/**
 * Guards a category_id taken from a request body: it must belong to the same wedding and have
 * the matching type. Enforced in the write actions, not only in Form Requests, so no caller
 * can attach a record to another user's category (IDOR) or a revenue to an expense category.
 *
 * One message for every failure, so the response never confirms that a foreign id exists.
 */
final class EnsureCategoryMatches
{
    /**
     * @throws ValidationException
     */
    public function handle(Wedding $wedding, mixed $categoryId, CategoryType $type): void
    {
        $matches = is_numeric($categoryId)
            && $wedding->categories()->ofType($type)->whereKey((int) $categoryId)->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'category_id' => __("The selected category is not one of this wedding's :type categories.", ['type' => $type->value]),
            ]);
        }
    }
}
