<?php

namespace App\Actions\Expenses;

use App\Exceptions\MissingExchangeRateException;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Services\CurrencyConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

use function Illuminate\Support\enum_value;

/**
 * The only write path for expense payments: create, update and delete all go through here,
 * so the cached expenses.paid_amount / base_paid_amount and the derived status can never
 * drift from the payments table.
 *
 * Each operation runs in one transaction and first locks the expense row, so concurrent
 * payments on the same expense are serialized.
 */
final class ManageExpensePayment
{
    public function __construct(private readonly CurrencyConversionService $conversion) {}

    /**
     * @param  array{amount: string, paid_date: string, payment_method?: ?string, notes?: ?string, currency?: string}  $data
     *
     * @throws ValidationException when the payment currency differs from the expense currency
     * @throws MissingExchangeRateException
     */
    public function create(Expense $expense, array $data): ExpensePayment
    {
        return DB::transaction(function () use ($expense, $data): ExpensePayment {
            $expense = $this->lockExpense($expense->getKey());
            $this->ensureExpenseCurrency($expense, $data);

            $payment = $expense->payments()->make(Arr::except($data, 'currency'));
            $payment->currency = $expense->currency;
            $this->snapshotRate($expense, $payment);
            $payment->save();

            $expense->refreshPaymentTotals();

            return $payment->setRelation('expense', $expense);
        });
    }

    /**
     * A new paid_date takes a new rate snapshot. A corrected amount on the same date keeps the
     * original rate, so later edits in Settings never leak into an existing payment.
     *
     * @param  array{amount?: string, paid_date?: string, payment_method?: ?string, notes?: ?string, currency?: string}  $data
     *
     * @throws ValidationException when the payment currency differs from the expense currency
     * @throws MissingExchangeRateException
     */
    public function update(ExpensePayment $payment, array $data): ExpensePayment
    {
        return DB::transaction(function () use ($payment, $data): ExpensePayment {
            $expense = $this->lockExpense($payment->expense_id);
            $this->ensureExpenseCurrency($expense, $data);

            $payment = ExpensePayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $payment->fill(Arr::except($data, 'currency'));

            if ($payment->isDirty('paid_date')) {
                $this->snapshotRate($expense, $payment);
            } elseif ($payment->isDirty('amount')) {
                $payment->base_amount = $this->conversion->convert($payment->amount, $payment->exchange_rate);
            }

            $payment->save();

            $expense->refreshPaymentTotals();

            return $payment->setRelation('expense', $expense);
        });
    }

    /**
     * Store an uploaded receipt (already validated for MIME type and size) on the private disk,
     * replacing any previous one. The file is written first under a random name; if the database
     * update fails it is removed again, and the replaced file is deleted only after the commit.
     */
    public function attachReceipt(ExpensePayment $payment, UploadedFile $file): ExpensePayment
    {
        $disk = Storage::disk('receipts');
        // Random file name from putFile(): the client's file name never becomes part of a path.
        $path = $disk->putFile("payments/{$payment->getKey()}", $file);

        if ($path === false) {
            throw new RuntimeException('The receipt could not be stored.');
        }

        try {
            [$payment, $previousPath] = DB::transaction(function () use ($payment, $file, $path): array {
                $expense = $this->lockExpense($payment->expense_id);
                $payment = ExpensePayment::query()->lockForUpdate()->findOrFail($payment->getKey());
                $previousPath = $payment->receipt_path;

                $payment->forceFill([
                    'receipt_path' => $path,
                    // Display name only; never used to build a path.
                    'receipt_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'receipt_mime_type' => $file->getMimeType(),
                    'receipt_size' => $file->getSize(),
                ])->save();

                return [$payment->setRelation('expense', $expense), $previousPath];
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($previousPath !== null) {
            $disk->delete($previousPath);
        }

        return $payment;
    }

    public function removeReceipt(ExpensePayment $payment): ExpensePayment
    {
        [$payment, $previousPath] = DB::transaction(function () use ($payment): array {
            $expense = $this->lockExpense($payment->expense_id);
            $payment = ExpensePayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $previousPath = $payment->receipt_path;

            $payment->forceFill([
                'receipt_path' => null,
                'receipt_original_name' => null,
                'receipt_mime_type' => null,
                'receipt_size' => null,
            ])->save();

            return [$payment->setRelation('expense', $expense), $previousPath];
        });

        if ($previousPath !== null) {
            Storage::disk('receipts')->delete($previousPath);
        }

        return $payment;
    }

    public function delete(ExpensePayment $payment): void
    {
        $receiptPath = DB::transaction(function () use ($payment): ?string {
            $expense = $this->lockExpense($payment->expense_id);

            $payment = ExpensePayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $receiptPath = $payment->receipt_path;
            $payment->delete();

            $expense->refreshPaymentTotals();

            return $receiptPath;
        });

        // Only after the commit: a rolled-back transaction cannot bring a deleted file back.
        if ($receiptPath !== null) {
            Storage::disk('receipts')->delete($receiptPath);
        }
    }

    private function lockExpense(int $expenseId): Expense
    {
        return Expense::query()->with('wedding')->lockForUpdate()->findOrFail($expenseId);
    }

    /**
     * Status and outstanding balance are derived in the expense currency, so a payment in any
     * other currency is rejected rather than converted. (A composite FK backs this in the DB.)
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function ensureExpenseCurrency(Expense $expense, array $data): void
    {
        if (! array_key_exists('currency', $data)) {
            return;
        }

        if (enum_value($data['currency']) !== $expense->currency->value) {
            throw ValidationException::withMessages([
                'currency' => __('Payments must be in the expense currency (:currency).', ['currency' => $expense->currency->value]),
            ]);
        }
    }

    /**
     * Snapshot the rate effective on the payment date and the resulting base amount.
     *
     * @throws MissingExchangeRateException
     */
    private function snapshotRate(Expense $expense, ExpensePayment $payment): void
    {
        $rate = $this->conversion->rateToBase($expense->wedding, $expense->currency, $payment->paid_date);

        $payment->exchange_rate = (string) $rate;
        $payment->base_amount = $this->conversion->convert($payment->amount, $rate);
    }
}
