<?php

use App\Actions\Expenses\ManageExpensePayment;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Exceptions\MissingExchangeRateException;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A USD venue invoiced at 6,800 in a LAK wedding with August and October USD rates.
 */
function usdVenue(): Expense
{
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21650.00000000', 'effective_date' => '2026-08-01']);
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);

    return Expense::factory()->for($wedding)->inCurrency(Currency::USD, '21850.00000000', '6500.00')->invoiced('6800.00')->create();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function pay(Expense $expense, string $amount, string $paidDate, array $overrides = []): ExpensePayment
{
    return app(ManageExpensePayment::class)->create($expense, ['amount' => $amount, 'paid_date' => $paidDate, ...$overrides]);
}

it('snapshots the rate effective on the payment date', function () {
    $expense = usdVenue();

    $payment = pay($expense, '2000.00', '2026-08-20');

    expect($payment->refresh())
        ->currency->toBe(Currency::USD)
        ->exchange_rate->toBe('21650.00000000')
        ->base_amount->toBe('43300000.00')
        ->and($expense->refresh())
        ->paid_amount->toBe('2000.00')
        ->base_paid_amount->toBe('43300000.00')
        ->status->toBe(ExpenseStatus::PartiallyPaid)
        ->outstanding_amount->toBe('4800.00');
});

it('derives status in the expense currency even when rates moved between payments', function () {
    $expense = usdVenue();

    pay($expense, '2000.00', '2026-08-20');
    pay($expense, '4800.00', '2026-10-20');

    // Base totals differ (148,180,000 paid vs 148,580,000 actual) only because the rate moved.
    expect($expense->refresh())
        ->paid_amount->toBe('6800.00')
        ->base_paid_amount->toBe('148180000.00')
        ->base_actual_amount->toBe('148580000.00')
        ->status->toBe(ExpenseStatus::Paid)
        ->is_overpaid->toBeFalse()
        ->outstanding_amount->toBe('0.00');
});

it('accepts an overpayment and flags it', function () {
    $expense = usdVenue();

    pay($expense, '7000.00', '2026-10-20');

    expect($expense->refresh())
        ->status->toBe(ExpenseStatus::Paid)
        ->is_overpaid->toBeTrue()
        ->outstanding_amount->toBe('0.00');
});

it('keeps a cancelled expense cancelled and counts its forfeited deposit', function () {
    $expense = usdVenue();
    $expense->update(['status' => ExpenseStatus::Cancelled]);

    pay($expense, '1000.00', '2026-10-20');

    expect($expense->refresh())
        ->status->toBe(ExpenseStatus::Cancelled)
        ->paid_amount->toBe('1000.00')
        ->outstanding_amount->toBe('0.00');
});

it('rejects a payment in another currency and saves nothing', function () {
    $expense = usdVenue();

    expect(fn () => pay($expense, '2000.00', '2026-10-20', ['currency' => 'LAK']))
        ->toThrow(ValidationException::class, 'Payments must be in the expense currency (USD).')
        ->and($expense->payments()->count())->toBe(0)
        ->and($expense->refresh()->paid_amount)->toBe('0.00');
});

it('rejects a payment dated before any rate exists and saves nothing', function () {
    $expense = usdVenue();

    expect(fn () => pay($expense, '2000.00', '2026-07-31'))
        ->toThrow(MissingExchangeRateException::class)
        ->and($expense->payments()->count())->toBe(0);
});

it('keeps the original rate when only the amount is corrected', function () {
    $expense = usdVenue();
    $payment = pay($expense, '2000.00', '2026-08-20');
    $expense->wedding->exchangeRates()->where('effective_date', '2026-08-01')->update(['rate' => '30000.00000000']);

    app(ManageExpensePayment::class)->update($payment, ['amount' => '2500.00']);

    expect($payment->refresh())
        ->exchange_rate->toBe('21650.00000000')
        ->base_amount->toBe('54125000.00')
        ->and($expense->refresh()->base_paid_amount)->toBe('54125000.00');
});

it('takes a new rate snapshot when the payment date changes', function () {
    $expense = usdVenue();
    $payment = pay($expense, '2000.00', '2026-08-20');

    app(ManageExpensePayment::class)->update($payment, ['paid_date' => '2026-10-20']);

    expect($payment->refresh())
        ->exchange_rate->toBe('21850.00000000')
        ->base_amount->toBe('43700000.00');
});

it('deletes a payment with its receipt and recalculates the expense', function () {
    Storage::fake('receipts');
    Storage::disk('receipts')->put('payments/deposit.pdf', 'receipt');
    $expense = usdVenue();
    $payment = pay($expense, '6800.00', '2026-10-20');
    $payment->forceFill(['receipt_path' => 'payments/deposit.pdf'])->save();

    app(ManageExpensePayment::class)->delete($payment);

    expect(ExpensePayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and($expense->refresh())
        ->paid_amount->toBe('0.00')
        ->status->toBe(ExpenseStatus::Booked);
    Storage::disk('receipts')->assertMissing('payments/deposit.pdf');
});

it('keeps cached totals equal to a fresh SUM of payments after any sequence of changes', function () {
    $venue = usdVenue();
    $other = usdVenue();
    $actions = app(ManageExpensePayment::class);

    $first = pay($venue, '1000.00', '2026-08-20');
    $second = pay($venue, '2500.55', '2026-10-02');
    pay($other, '333.33', '2026-09-15');
    $actions->update($first, ['amount' => '1200.10']);
    $actions->update($second, ['paid_date' => '2026-08-31']);
    $actions->delete(pay($venue, '50.00', '2026-11-01'));

    $fresh = DB::table('expense_payments')
        ->selectRaw('expense_id, SUM(amount) AS paid, SUM(base_amount) AS base_paid')
        ->groupBy('expense_id')
        ->get()
        ->keyBy('expense_id');

    foreach ([$venue, $other] as $expense) {
        expect($expense->refresh())
            ->paid_amount->toBe($fresh[$expense->id]->paid)
            ->base_paid_amount->toBe($fresh[$expense->id]->base_paid);
    }
});

it('deletes the replaced receipt once the new one is saved', function () {
    Storage::fake('receipts');
    $payment = pay(usdVenue(), '2000.00', '2026-10-20');
    $actions = app(ManageExpensePayment::class);
    $first = $actions->attachReceipt($payment, UploadedFile::fake()->create('deposit.pdf', 10, 'application/pdf'))->receipt_path;

    $second = $actions->attachReceipt($payment, UploadedFile::fake()->create('deposit-signed.pdf', 10, 'application/pdf'))->receipt_path;

    expect($payment->refresh()->receipt_path)->toBe($second)->not->toBe($first);
    Storage::disk('receipts')->assertMissing($first);
    Storage::disk('receipts')->assertExists($second);
});

it('removes the stored file when the receipt cannot be recorded, leaving no orphan', function () {
    Storage::fake('receipts');
    $payment = pay(usdVenue(), '2000.00', '2026-10-20');
    // Deleted by a concurrent request between the policy check and the upload.
    ExpensePayment::query()->whereKey($payment->id)->delete();

    expect(fn () => app(ManageExpensePayment::class)->attachReceipt($payment, UploadedFile::fake()->create('deposit.pdf', 10, 'application/pdf')))
        ->toThrow(ModelNotFoundException::class);

    expect(Storage::disk('receipts')->allFiles())->toBe([]);
});
