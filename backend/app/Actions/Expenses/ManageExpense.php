<?php

namespace App\Actions\Expenses;

use App\Actions\Categories\EnsureCategoryMatches;
use App\Enums\CategoryType;
use App\Enums\ExpenseStatus;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Expense;
use App\Models\Wedding;
use App\Services\CurrencyConversionService;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Creates, updates and deletes expenses with their rate snapshot (docs/business-rules.md):
 * - the rate is taken on the day the expense is saved and shared by estimate and actual;
 * - a new snapshot is taken only when the currency changes (blocked once payments exist);
 * - amount edits, including setting actual_amount later, reuse the stored rate.
 * Status is user-settable only as planned / booked / cancelled; after every update the
 * payment-derived status is recomputed, so a new actual_amount can turn it into paid.
 */
final class ManageExpense
{
    public function __construct(
        private readonly CurrencyConversionService $conversion,
        private readonly EnsureCategoryMatches $ensureCategory,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input; never exchange_rate, base_* or paid amounts
     *
     * @throws ValidationException
     * @throws MissingExchangeRateException
     */
    public function create(Wedding $wedding, array $data): Expense
    {
        $this->ensureUserSettableStatus($data);

        return DB::transaction(function () use ($wedding, $data): Expense {
            $wedding = $this->lockWedding($wedding->getKey());
            $this->ensureCategory->handle($wedding, $data['category_id'] ?? null, CategoryType::Expense);

            $expense = $wedding->expenses()->make($data);
            $expense->setRelation('wedding', $wedding);

            if (! array_key_exists('currency', $data)) {
                $expense->currency = $wedding->base_currency;
            }

            $this->snapshot($wedding, $expense);
            $expense->save();

            return $expense;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input; never exchange_rate, base_* or paid amounts
     *
     * @throws ValidationException
     * @throws MissingExchangeRateException
     */
    public function update(Expense $expense, array $data): Expense
    {
        $this->ensureUserSettableStatus($data);

        return DB::transaction(function () use ($expense, $data): Expense {
            $wedding = $this->lockWedding($expense->wedding_id);
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            $expense->setRelation('wedding', $wedding);

            if (array_key_exists('category_id', $data)) {
                $this->ensureCategory->handle($wedding, $data['category_id'], CategoryType::Expense);
            }

            $expense->fill($data);

            if ($expense->isDirty('currency')) {
                $this->ensureNoPayments($expense);
                $this->snapshot($wedding, $expense);
            } elseif ($expense->isDirty(['estimated_amount', 'actual_amount'])) {
                $this->convertAmounts($expense, new Number($expense->exchange_rate));
            }

            $expense->save();
            $expense->refreshPaymentTotals();

            return $expense;
        });
    }

    /**
     * Soft delete: the record leaves every total but stays available for audit and restore.
     * Its payments stay attached and come back with it.
     */
    public function delete(Expense $expense): void
    {
        $expense->delete();
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
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function ensureUserSettableStatus(array $data): void
    {
        if (! array_key_exists('status', $data)) {
            return;
        }

        $status = ExpenseStatus::tryFrom((string) enum_value($data['status']));

        if ($status === null || ! $status->isUserSettable()) {
            throw ValidationException::withMessages([
                'status' => __('Status can be planned, booked or cancelled. Partially paid and paid follow from payments.'),
            ]);
        }
    }

    /**
     * Payments must stay in the expense currency (paid vs actual is compared in it).
     *
     * @throws ValidationException
     */
    private function ensureNoPayments(Expense $expense): void
    {
        if ($expense->payments()->exists()) {
            throw ValidationException::withMessages([
                'currency' => __('The currency cannot change once payments are recorded.'),
            ]);
        }
    }

    /**
     * Rate of the day the snapshot is taken: creation day, or the day the currency is changed.
     *
     * @throws MissingExchangeRateException
     */
    private function snapshot(Wedding $wedding, Expense $expense): void
    {
        $rate = $this->conversion->rateToBase($wedding, $expense->currency, now());

        $expense->exchange_rate = (string) $rate;
        $this->convertAmounts($expense, $rate);
    }

    private function convertAmounts(Expense $expense, Number $rate): void
    {
        $expense->base_estimated_amount = $this->conversion->convert($expense->estimated_amount, $rate);
        $expense->base_actual_amount = $expense->actual_amount === null
            ? null
            : $this->conversion->convert($expense->actual_amount, $rate);
    }
}
