<?php

use App\Actions\Expenses\ManageExpense;
use App\Actions\Expenses\ManageExpensePayment;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

/**
 * A LAK wedding with a USD rate (21,850) and a THB rate (628.40), both effective 2026-10-01.
 */
function weddingForExpenses(): Wedding
{
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '628.40000000', 'effective_date' => '2026-10-01']);

    return $wedding;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createExpense(Wedding $wedding, array $overrides = []): Expense
{
    return app(ManageExpense::class)->create($wedding, [
        'category_id' => Category::factory()->for($wedding)->create()->id,
        'description' => 'Banquet hall',
        'currency' => 'USD',
        'estimated_amount' => '6500.00',
        ...$overrides,
    ]);
}

beforeEach(fn () => $this->travelTo('2026-11-18'));

it('snapshots the rate of the save date for estimate and actual', function () {
    $expense = createExpense(weddingForExpenses(), ['actual_amount' => '6800.00']);

    expect($expense->refresh())
        ->exchange_rate->toBe('21850.00000000')
        ->base_estimated_amount->toBe('142025000.00')
        ->base_actual_amount->toBe('148580000.00')
        ->status->toBe(ExpenseStatus::Planned);
});

it('fails with a 422 and saves nothing when no rate exists yet', function () {
    $wedding = Wedding::factory()->create();

    expect(fn () => createExpense($wedding))
        ->toThrow(MissingExchangeRateException::class, 'No USD to LAK exchange rate is set on or before 2026-11-18.')
        ->and($wedding->expenses()->count())->toBe(0);
});

it('reuses the stored rate when the actual amount arrives later', function () {
    $wedding = weddingForExpenses();
    $expense = createExpense($wedding);
    $this->travelTo('2026-12-20');
    ExchangeRate::factory()->for($wedding)->create(['rate' => '23000.00000000', 'effective_date' => '2026-12-01']);

    app(ManageExpense::class)->update($expense, ['actual_amount' => '6800.00']);

    expect($expense->refresh())
        ->exchange_rate->toBe('21850.00000000')
        ->base_actual_amount->toBe('148580000.00');
});

it('takes a new snapshot when the currency changes', function () {
    $expense = createExpense(weddingForExpenses());

    app(ManageExpense::class)->update($expense, ['currency' => 'THB', 'estimated_amount' => '200000.00']);

    expect($expense->refresh())
        ->currency->toBe(Currency::THB)
        ->exchange_rate->toBe('628.40000000')
        ->base_estimated_amount->toBe('125680000.00');
});

it('blocks a currency change once payments exist', function () {
    $expense = createExpense(weddingForExpenses());
    app(ManageExpensePayment::class)->create($expense, ['amount' => '1000.00', 'paid_date' => '2026-11-01']);

    expect(fn () => app(ManageExpense::class)->update($expense, ['currency' => 'THB']))
        ->toThrow(ValidationException::class, 'The currency cannot change once payments are recorded.')
        ->and($expense->refresh()->currency)->toBe(Currency::USD);
});

it('rejects statuses that are derived from payments', function (string $status) {
    $wedding = weddingForExpenses();

    expect(fn () => createExpense($wedding, ['status' => $status]))
        ->toThrow(ValidationException::class, 'Partially paid and paid follow from payments.');
})->with(['partially_paid', 'paid', 'unknown']);

it('re-derives the status when the actual amount changes', function () {
    $expense = createExpense(weddingForExpenses(), ['status' => 'booked']);
    app(ManageExpensePayment::class)->create($expense, ['amount' => '6500.00', 'paid_date' => '2026-11-01']);
    expect($expense->refresh()->status)->toBe(ExpenseStatus::PartiallyPaid);

    app(ManageExpense::class)->update($expense, ['actual_amount' => '6500.00']);

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Paid);
});

it('keeps the derived status when the user re-books a paid expense', function () {
    $expense = createExpense(weddingForExpenses(), ['actual_amount' => '1000.00']);
    app(ManageExpensePayment::class)->create($expense, ['amount' => '1000.00', 'paid_date' => '2026-11-01']);

    app(ManageExpense::class)->update($expense, ['status' => 'booked']);

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Paid);
});

it('defaults the currency to the wedding base currency at rate 1', function () {
    $wedding = Wedding::factory()->create(['base_currency' => Currency::THB]);

    $expense = app(ManageExpense::class)->create($wedding, [
        'category_id' => Category::factory()->for($wedding)->create()->id,
        'description' => 'Flowers',
        'estimated_amount' => '15000.00',
    ]);

    expect($expense->refresh())
        ->currency->toBe(Currency::THB)
        ->exchange_rate->toBe('1.00000000')
        ->base_estimated_amount->toBe('15000.00');
});
