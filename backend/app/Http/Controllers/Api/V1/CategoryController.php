<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Categories\DeleteCategory;
use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Category,wedding', only: ['index']),
            new Middleware('can:create,App\Models\Category,wedding', only: ['store']),
            new Middleware('can:update,category', only: ['update']),
            new Middleware('can:delete,category', only: ['destroy']),
        ];
    }

    /**
     * Not paginated: a wedding has a few dozen categories at most, and forms need all of them.
     */
    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $validated = $request->validate(['type' => ['sometimes', Rule::enum(CategoryType::class)]]);

        $categories = $wedding->categories()
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request, Wedding $wedding): JsonResponse
    {
        $category = $wedding->categories()->create($request->validated());

        return CategoryResource::make($category->setRelation('wedding', $wedding))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Wedding $wedding, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($category);
    }

    /**
     * 409 category_in_use when revenues or expenses still reference it (CategoryInUseException).
     */
    public function destroy(Wedding $wedding, Category $category, DeleteCategory $deleteCategory): Response
    {
        $deleteCategory->handle($category);

        return response()->noContent();
    }
}
