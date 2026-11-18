<?php

use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpensePayment;

it('caches payment sums, each at the rate of its own payment', function () {
    $expense = Expense::factory()->inCurrency(Currency::USD, '21850.00000000', '6500.00')->invoiced('6800.00')->create();

    ExpensePayment::factory()->for($expense)->create(['amount' => '2000.00', 'exchange_rate' => '21650.00000000']);
    ExpensePayment::factory()->for($expense)->create(['amount' => '2000.00', 'exchange_rate' => '21850.00000000']);

    $expense->refresh();

    expect($expense->paid_amount)->toBe('4000.00')
        ->and($expense->base_paid_amount)->toBe('87000000.00')
        ->and($expense->status)->toBe(ExpenseStatus::PartiallyPaid);
});

it('marks the expense paid once payments reach the actual amount', function () {
    $expense = Expense::factory()->paid()->create();

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Paid)
        ->and($expense->paid_amount)->toBe($expense->actual_amount);
});

it('falls back to booked when the last payment is removed', function () {
    $expense = Expense::factory()->paid()->create();

    $expense->payments()->delete();
    $expense->refreshPaymentTotals();

    expect($expense->refresh())
        ->status->toBe(ExpenseStatus::Booked)
        ->paid_amount->toBe('0.00')
        ->base_paid_amount->toBe('0.00');
});

it('computes the outstanding amount in the expense currency', function (Closure $expense, string $expected) {
    expect($expense()->outstanding_amount)->toBe($expected);
})->with([
    'estimate before an invoice' => [fn () => Expense::factory()->create(['estimated_amount' => '1000.00']), '1000.00'],
    'actual minus paid' => [fn () => Expense::factory()->invoiced('6800.00')->create(['paid_amount' => '2000.00']), '4800.00'],
    'never below zero when overpaid' => [fn () => Expense::factory()->invoiced('6800.00')->create(['paid_amount' => '7000.00']), '0.00'],
    'nothing left on a cancelled expense' => [fn () => Expense::factory()->cancelled()->create(['estimated_amount' => '1000.00', 'paid_amount' => '300.00']), '0.00'],
]);
