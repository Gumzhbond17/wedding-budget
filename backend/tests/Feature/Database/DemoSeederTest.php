<?php

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Revenue;
use App\Models\User;
use BcMath\Number;
use Database\Seeders\DatabaseSeeder;

function expectedBase(?string $amount, string $rate): ?string
{
    return $amount === null ? null : (string) (new Number($amount) * new Number($rate))->round(2);
}

it('seeds a demo wedding with records in LAK, USD and THB', function () {
    $this->seed(DatabaseSeeder::class);

    $wedding = User::query()->where('email', 'demo@example.com')->sole()->weddings()->sole();

    expect($wedding->categories()->count())->toBe(18)
        ->and($wedding->revenues()->distinct()->pluck('currency')->map->value->all())->toEqualCanonicalizing(['LAK', 'USD', 'THB'])
        ->and($wedding->expenses()->distinct()->pluck('currency')->map->value->all())->toEqualCanonicalizing(['LAK', 'USD', 'THB']);
});

it('seeds snapshots that match amount times rate', function () {
    $this->seed(DatabaseSeeder::class);

    Revenue::all()->each(fn (Revenue $revenue) => expect($revenue->base_amount)
        ->toBe(expectedBase($revenue->amount, $revenue->exchange_rate)));

    Expense::all()->each(fn (Expense $expense) => expect([$expense->base_estimated_amount, $expense->base_actual_amount])
        ->toBe([expectedBase($expense->estimated_amount, $expense->exchange_rate), expectedBase($expense->actual_amount, $expense->exchange_rate)]));

    ExpensePayment::all()->each(fn (ExpensePayment $payment) => expect($payment->base_amount)
        ->toBe(expectedBase($payment->amount, $payment->exchange_rate)));
});

it('seeds paid totals equal to the sum of payments', function () {
    $this->seed(DatabaseSeeder::class);

    Expense::query()->withSum('payments', 'amount')->withSum('payments', 'base_amount')->get()
        ->each(fn (Expense $expense) => expect([$expense->paid_amount, $expense->base_paid_amount])
            ->toBe([$expense->payments_sum_amount ?? '0.00', $expense->payments_sum_base_amount ?? '0.00']));
});
