<?php

namespace App\Actions\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Collection;

/**
 * All categories of a wedding, optionally of one type. Not paginated: a wedding has a few
 * dozen categories at most, and forms need all of them.
 */
final class ListCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(Wedding $wedding, ?CategoryType $type = null): Collection
    {
        return $wedding->categories()
            ->when($type, fn ($query, CategoryType $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }
}
