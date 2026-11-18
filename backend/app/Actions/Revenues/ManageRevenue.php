<?php

namespace App\Actions\Revenues;

use App\Actions\Categories\EnsureCategoryMatches;
use App\Enums\CategoryType;
use App\Enums\RevenueStatus;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Revenue;
use App\Models\Wedding;
use App\Services\CurrencyConversionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, updates and deletes revenue with its rate snapshot (docs/business-rules.md):
 * - transaction date = received_date, else expected_date, else the day it was saved;
 * - a new snapshot is taken when the currency or the transaction date changes;
 * - an amount-only edit reuses the stored rate, so later rate edits never leak in.
 */
final class ManageRevenue
{
    public function __construct(
        private readonly CurrencyConversionService $conversion,
        private readonly EnsureCategoryMatches $ensureCategory,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input; never exchange_rate or base_amount
     *
     * @throws ValidationException
     * @throws MissingExchangeRateException
     */
    public function create(Wedding $wedding, array $data): Revenue
    {
        return DB::transaction(function () use ($wedding, $data): Revenue {
            $wedding = $this->lockWedding($wedding->getKey());
            $this->ensureCategory->handle($wedding, $data['category_id'] ?? null, CategoryType::Revenue);

            $revenue = $wedding->revenues()->make($data);
            $revenue->setRelation('wedding', $wedding);

            if (! array_key_exists('currency', $data)) {
                $revenue->currency = $wedding->base_currency;
            }

            $this->ensureReceivedDate($revenue);
            $this->snapshot($wedding, $revenue);
            $revenue->save();

            return $revenue;
        });
    }

    /**
     * Marking an expected revenue as received moves its transaction date to received_date,
     * so it is re-snapshotted at the rate of the day the money actually arrived.
     *
     * @param  array<string, mixed>  $data  validated input; never exchange_rate or base_amount
     *
     * @throws ValidationException
     * @throws MissingExchangeRateException
     */
    public function update(Revenue $revenue, array $data): Revenue
    {
        return DB::transaction(function () use ($revenue, $data): Revenue {
            $wedding = $this->lockWedding($revenue->wedding_id);
            $revenue = Revenue::query()->lockForUpdate()->findOrFail($revenue->getKey());
            $revenue->setRelation('wedding', $wedding);

            if (array_key_exists('category_id', $data)) {
                $this->ensureCategory->handle($wedding, $data['category_id'], CategoryType::Revenue);
            }

            $dateBefore = $this->transactionDate($revenue)->toDateString();
            $revenue->fill($data);
            $this->ensureReceivedDate($revenue);

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

    /**
     * A received revenue needs the date it arrived: that date picks its rate snapshot.
     *
     * @throws ValidationException
     */
    private function ensureReceivedDate(Revenue $revenue): void
    {
        if ($revenue->status === RevenueStatus::Received && $revenue->received_date === null) {
            throw ValidationException::withMessages([
                'received_date' => 'A received revenue needs the date it was received.',
            ]);
        }
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
