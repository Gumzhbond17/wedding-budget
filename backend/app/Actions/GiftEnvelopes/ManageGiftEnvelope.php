<?php

namespace App\Actions\GiftEnvelopes;

use App\Models\GiftEnvelope;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates and deletes gift envelopes. Amounts are in the wedding's base currency,
 * so there is no rate snapshot; the shared wedding lock is what keeps them valid: it waits
 * for UpdateWedding, which changes base_currency only under an exclusive lock and only while
 * no envelope exists.
 */
final class ManageGiftEnvelope
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(Wedding $wedding, array $data): GiftEnvelope
    {
        return DB::transaction(function () use ($wedding, $data): GiftEnvelope {
            $wedding = Wedding::query()->sharedLock()->findOrFail($wedding->getKey());

            $envelope = $wedding->giftEnvelopes()->make($data);
            $envelope->setRelation('wedding', $wedding);
            $envelope->save();

            return $envelope;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(GiftEnvelope $envelope, array $data): GiftEnvelope
    {
        $envelope->fill($data)->save();

        return $envelope;
    }

    /**
     * Soft delete: the envelope leaves every total but stays available for audit and restore.
     */
    public function delete(GiftEnvelope $envelope): void
    {
        $envelope->delete();
    }
}
