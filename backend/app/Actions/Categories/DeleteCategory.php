<?php

namespace App\Actions\Categories;

use App\Exceptions\CategoryInUseException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a category only when no revenue or expense row references it.
 *
 * The RESTRICT foreign keys are the check itself: attempting the delete and translating
 * MySQL error 1451 is atomic, whereas "count, then delete" races with a concurrent insert.
 * Soft-deleted records still count as in use, because their history must stay intact.
 */
final class DeleteCategory
{
    /** MySQL ER_ROW_IS_REFERENCED_2: "Cannot delete or update a parent row". */
    private const int MYSQL_ROW_IS_REFERENCED = 1451;

    /**
     * @throws CategoryInUseException
     */
    public function handle(int $categoryId): void
    {
        try {
            DB::table('categories')->where('id', $categoryId)->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::MYSQL_ROW_IS_REFERENCED) {
                throw new CategoryInUseException(previous: $exception);
            }

            throw $exception;
        }
    }
}
