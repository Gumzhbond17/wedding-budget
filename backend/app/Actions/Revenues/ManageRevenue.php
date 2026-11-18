<?php

namespace App\Actions\Revenues;

use App\Exceptions\MissingExchangeRateException;
use App\Models\Revenue;
use App\Models\Wedding;
use App\Services\CurrencyConversionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates and deletes revenue with its rate snapshot (docs/business-rules.md):
 * - transaction date = received_date, else expected_date, else the day it was saved;
 * - a new snapshot is taken when the currency or the transaction date changes;
 * - an amount-only edit reuses the stored rate, so later rate edits never leak in.
 */
final class ManageRevenue
{
    public function __construct(private readonly CurrencyConversionService $conversion) {}

    /**
     * @param  array<string, mixed>  $data  validated input; never exchange_rate or base_amount
     *
     * @throws MissingExchangeRateException
     */
    public function create(Wedding $wedding, array $data): Revenue
    {
        return DB::transaction(function () use ($wedding, $data): Revenue {
            $wedding = $this->lockWedding($wedding->getKey());

            $revenue = $wedding->revenues()->make($data);
            $revenue->setRelation('wedding', $wedding);

            if (! array_key_exists('currency', $data)) {
                $revenue->currency = $wedding->base_currency;
            }

            $this->snapshot($wedding, $revenue);
            $revenue->save();

            return $revenue;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input; never exchange_rate or base_amount
     *
     * @throws MissingExchangeRateException
     */
    public function update(Revenue $revenue, array $data): Revenue
    {
        return DB::transaction(function () use ($revenue, $data): Revenue {
            $wedding = $this->lockWedding($revenue->wedding_id);
            $revenue = Revenue::query()->lockForUpdate()->findOrFail($revenue->getKey());
            $revenue->setRelation('wedding', $wedding);

            $dateBefore = $this->transactionDate($revenue)->toDateString();
            $revenue->fill($data);

            if ($revenue->isDirty('currency') || $this->transactionDate($revenue)->toDateString() !== $dateBefore) {
                $this->snapshot($wedding, $revenue);
            } elseif ($revenue->isDirty('amount')) {
                $revenue->base_amount = $this->conversion->convert($revenue->amount, $revenue->exchange_rate);
            }

            $revenue->save();

            return $revenue;
        });
    }

    /**
     * Soft delete: the record leaves every total but stays available for audit and restore.
     */
    public function delete(Revenue $revenue): void
    {
        $revenue->delete();
    }

    /**
     * Shared lock: many records can be written at once, but never while UpdateWedding is
     * changing base_currency under its exclusive lock.
     */
    private function lockWedding(int $weddingId): Wedding
    {
        return Wedding::query()->sharedLock()->findOrFail($weddingId);
    }

    private function transactionDate(Revenue $revenue): CarbonInterface
    {
        return $revenue->received_date ?? $revenue->expected_date ?? $revenue->created_at ?? now();
    }

    /**
     * @throws MissingExchangeRateException
     */
    private function snapshot(Wedding $wedding, Revenue $revenue): void
    {
        $rate = $this->conversion->rateToBase($wedding, $revenue->currency, $this->transactionDate($revenue));

        $revenue->exchange_rate = (string) $rate;
        $revenue->base_amount = $this->conversion->convert($revenue->amount, $rate);
    }
}
