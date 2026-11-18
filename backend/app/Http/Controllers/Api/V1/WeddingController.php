<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Weddings\CreateWedding;
use App\Actions\Weddings\DeleteWedding;
use App\Actions\Weddings\UpdateWedding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Weddings\StoreWeddingRequest;
use App\Http\Requests\Weddings\UpdateWeddingRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WeddingController extends Controller implements HasMiddleware
{
    /**
     * Authorization runs as middleware, after route binding but before Form Request validation,
     * so another user's id gets the same 404 as a missing one, whatever the request body.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Wedding', only: ['index']),
            new Middleware('can:create,App\Models\Wedding', only: ['store']),
            new Middleware('can:view,wedding', only: ['show']),
            new Middleware('can:update,wedding', only: ['update']),
            new Middleware('can:delete,wedding', only: ['destroy']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $weddings = $request->user()->weddings()
            ->orderByDesc('wedding_date')
            ->orderByDesc('id')
            ->paginate(25);

        return WeddingResource::collection($weddings);
    }

    public function store(StoreWeddingRequest $request, CreateWedding $createWedding): JsonResponse
    {
        $wedding = $createWedding->handle($request->user(), $request->validated());

        return WeddingResource::make($wedding->refresh())->response()->setStatusCode(201);
    }

    public function show(Wedding $wedding): WeddingResource
    {
        return WeddingResource::make($wedding);
    }

    public function update(UpdateWeddingRequest $request, Wedding $wedding, UpdateWedding $updateWedding): WeddingResource
    {
        return WeddingResource::make($updateWedding->handle($wedding, $request->validated()));
    }

    public function destroy(Wedding $wedding, DeleteWedding $deleteWedding): Response
    {
        $deleteWedding->handle($wedding);

        return response()->noContent();
    }
}
