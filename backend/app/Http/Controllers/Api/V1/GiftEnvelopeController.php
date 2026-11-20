<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GiftEnvelopes\ListGiftEnvelopes;
use App\Actions\GiftEnvelopes\ManageGiftEnvelope;
use App\Actions\GiftEnvelopes\SummarizeGiftEnvelopes;
use App\Http\Controllers\Controller;
use App\Http\Requests\GiftEnvelopes\ListGiftEnvelopesRequest;
use App\Http\Requests\GiftEnvelopes\StoreGiftEnvelopeRequest;
use App\Http\Requests\GiftEnvelopes\UpdateGiftEnvelopeRequest;
use App\Http\Resources\GiftEnvelopeResource;
use App\Models\GiftEnvelope;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GiftEnvelopeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\GiftEnvelope,wedding', only: ['index']),
            new Middleware('can:create,App\Models\GiftEnvelope,wedding', only: ['store']),
            new Middleware('can:view,giftEnvelope', only: ['show']),
            new Middleware('can:update,giftEnvelope', only: ['update']),
            new Middleware('can:delete,giftEnvelope', only: ['destroy']),
        ];
    }

    /**
     * The page plus meta.summary: count and total per guest group for the whole wedding.
     */
    public function index(ListGiftEnvelopesRequest $request, Wedding $wedding, ListGiftEnvelopes $envelopes, SummarizeGiftEnvelopes $summary): AnonymousResourceCollection
    {
        $totals = $summary->handle($wedding);
        $money = fn (string $amount): array => ['amount' => $amount, 'currency' => $wedding->base_currency->value];

        return GiftEnvelopeResource::collection($envelopes->handle($wedding, $request->criteria('-created_at')))
            ->additional(['meta' => ['summary' => [
                'count' => $totals['count'],
                'total' => $money($totals['total']),
                'groups' => array_map(fn (array $group): array => [
                    'guest_group' => $group['guest_group']->value,
                    'count' => $group['count'],
                    'total' => $money($group['total']),
                ], $totals['groups']),
            ]]]);
    }

    public function store(StoreGiftEnvelopeRequest $request, Wedding $wedding, ManageGiftEnvelope $envelopes): JsonResponse
    {
        $envelope = $envelopes->create($wedding, $request->validated());

        return GiftEnvelopeResource::make($envelope)->response()->setStatusCode(201);
    }

    public function show(Wedding $wedding, GiftEnvelope $giftEnvelope): GiftEnvelopeResource
    {
        return GiftEnvelopeResource::make($giftEnvelope);
    }

    public function update(UpdateGiftEnvelopeRequest $request, Wedding $wedding, GiftEnvelope $giftEnvelope, ManageGiftEnvelope $envelopes): GiftEnvelopeResource
    {
        return GiftEnvelopeResource::make($envelopes->update($giftEnvelope, $request->validated()));
    }

    /**
     * Soft delete: excluded from totals, kept for history.
     */
    public function destroy(Wedding $wedding, GiftEnvelope $giftEnvelope, ManageGiftEnvelope $envelopes): Response
    {
        $envelopes->delete($giftEnvelope);

        return response()->noContent();
    }
}
