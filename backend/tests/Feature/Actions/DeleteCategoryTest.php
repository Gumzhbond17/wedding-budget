<?php

use App\Actions\Categories\DeleteCategory;
use App\Exceptions\CategoryInUseException;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

it('blocks deleting a category that has records and deletes nothing', function (string $category) {
    $ids = seedWeddingGraph();
    $before = weddingRowCounts($ids['wedding']);

    expect(fn () => app(DeleteCategory::class)->handle(Category::query()->findOrFail($ids[$category])))
        ->toThrow(CategoryInUseException::class)
        ->and(weddingRowCounts($ids['wedding']))->toBe($before);
})->with(['revenue_category', 'expense_category']);

it('blocks deleting a category used only by soft-deleted records', function () {
    $ids = seedWeddingGraph();
    DB::table('expenses')->where('id', $ids['expense'])->update(['deleted_at' => now()]);

    expect(fn () => app(DeleteCategory::class)->handle(Category::query()->findOrFail($ids['expense_category'])))
        ->toThrow(CategoryInUseException::class);
});

it('deletes a category that has no records', function () {
    $ids = seedWeddingGraph();
    $unused = DB::table('categories')->insertGetId(['wedding_id' => $ids['wedding'], 'type' => 'expense', 'name' => 'Honeymoon']);

    app(DeleteCategory::class)->handle(Category::query()->findOrFail($unused));

    expect(DB::table('categories')->where('id', $unused)->exists())->toBeFalse();
});

it('renders category in use as a 409 JSON error', function () {
    $response = (new CategoryInUseException)->render();

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toMatchArray(['code' => 'category_in_use']);
});
