<?php

namespace App\Http\Controllers\Api\V1;

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

    /**
     * Date range filters on the transaction date: received_date, else expected_date.
     */
    public function index(ListRevenuesRequest $request, Wedding $wedding): AnonymousResourceCollection
    {
        [$column, $direction] = $request->sortColumnAndDirection('-created_at');

        $revenues = $wedding->revenues()
            ->with('category')
            ->when($request->validated('category_id'), fn ($query, $id) => $query->where('category_id', $id))
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('currency'), fn ($query, $currency) => $query->where('currency', $currency))
            ->when($request->validated('date_from'), fn ($query, $from) => $query->whereRaw('COALESCE(received_date, expected_date) >= ?', [$from]))
            ->when($request->validated('date_to'), fn ($query, $to) => $query->whereRaw('COALESCE(received_date, expected_date) <= ?', [$to]))
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate($request->perPage())
            ->withQueryString();

        return RevenueResource::collection($revenues);
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
