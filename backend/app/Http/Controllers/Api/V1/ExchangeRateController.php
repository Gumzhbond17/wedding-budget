<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ExchangeRates\ListExchangeRates;
use App\Actions\ExchangeRates\ManageExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRates\StoreExchangeRateRequest;
use App\Http\Requests\ExchangeRates\UpdateExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExchangeRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\ExchangeRate,wedding', only: ['index']),
            new Middleware('can:create,App\Models\ExchangeRate,wedding', only: ['store']),
            new Middleware('can:update,exchangeRate', only: ['update']),
            new Middleware('can:delete,exchangeRate', only: ['destroy']),
        ];
    }

    public function index(Wedding $wedding, ListExchangeRates $rates): AnonymousResourceCollection
    {
        return ExchangeRateResource::collection($rates->handle($wedding));
    }

    public function store(StoreExchangeRateRequest $request, Wedding $wedding, ManageExchangeRate $rates): JsonResponse
    {
        $rate = $rates->create($wedding, $request->validated());

        return ExchangeRateResource::make($rate)->response()->setStatusCode(201);
    }

    public function update(UpdateExchangeRateRequest $request, Wedding $wedding, ExchangeRate $exchangeRate, ManageExchangeRate $rates): ExchangeRateResource
    {
        return ExchangeRateResource::make($rates->update($exchangeRate, $request->validated()));
    }

    /**
     * Existing records keep their own rate snapshot, so deleting a rate never changes them.
     */
    public function destroy(Wedding $wedding, ExchangeRate $exchangeRate, ManageExchangeRate $rates): Response
    {
        $rates->delete($exchangeRate);

        return response()->noContent();
    }
}
