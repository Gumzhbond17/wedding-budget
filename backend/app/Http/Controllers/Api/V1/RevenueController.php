<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Revenues\ListRevenues;
use App\Actions\Revenues\ManageRevenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Revenues\ListRevenuesRequest;
use App\Http\Requests\Revenues\StoreRevenueRequest;
use App\Http\Requests\Revenues\UpdateRevenueRequest;
use App\Http\Resources\RevenueResource;
use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RevenueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Revenue,wedding', only: ['index']),
            new Middleware('can:create,App\Models\Revenue,wedding', only: ['store']),
            new Middleware('can:view,revenue', only: ['show']),
            new Middleware('can:update,revenue', only: ['update']),
            new Middleware('can:delete,revenue', only: ['destroy']),
        ];
    }

    public function index(ListRevenuesRequest $request, Wedding $wedding, ListRevenues $revenues): AnonymousResourceCollection
    {
        return RevenueResource::collection($revenues->handle($wedding, $request->criteria('-created_at')));
    }

    public function store(StoreRevenueRequest $request, Wedding $wedding, ManageRevenue $revenues): JsonResponse
    {
        $revenue = $revenues->create($wedding, $request->validated());

        return RevenueResource::make($revenue->load('category'))->response()->setStatusCode(201);
    }

    public function show(Wedding $wedding, Revenue $revenue): RevenueResource
    {
        return RevenueResource::make($revenue->load('category'));
    }

    public function update(UpdateRevenueRequest $request, Wedding $wedding, Revenue $revenue, ManageRevenue $revenues): RevenueResource
    {
        return RevenueResource::make($revenues->update($revenue, $request->validated())->load('category'));
    }

    /**
     * Soft delete: excluded from totals, kept for history.
     */
    public function destroy(Wedding $wedding, Revenue $revenue, ManageRevenue $revenues): Response
    {
        $revenues->delete($revenue);

        return response()->noContent();
    }
}
