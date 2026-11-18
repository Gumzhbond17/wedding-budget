<?php

use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\User;
use App\Models\Wedding;

/**
 * @return array{0: User, 1: Wedding, 2: Category}
 */
function expenseOwner(): array
{
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);

    return [$user, $wedding, Category::factory()->for($wedding)->create()];
}

beforeEach(fn () => $this->travelTo('2026-11-18'));

it('creates an expense with original and base amounts and an estimated outstanding', function () {
    [$user, $wedding, $category] = expenseOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.store', $wedding), [
        'category_id' => $category->id, 'description' => 'Banquet hall', 'currency' => 'USD', 'estimated_amount' => '6500.00',
        'paid_amount' => '6500.00', 'base_estimated_amount' => '1.00',
    ])->assertCreated()
        ->assertJsonPath('data.estimated_amount', ['amount' => '6500.00', 'currency' => 'USD'])
        ->assertJsonPath('data.base_estimated_amount', ['amount' => '142025000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.paid_amount', ['amount' => '0.00', 'currency' => 'USD'])
        ->assertJsonPath('data.outstanding_amount', ['amount' => '6500.00', 'currency' => 'USD', 'basis' => 'estimated'])
        ->assertJsonPath('data.is_overpaid', false)
        ->assertJsonPath('data.status', 'planned');
});

it('labels outstanding as actual once the invoice is in, and flags overpayment', function () {
    [$user, $wedding] = expenseOwner();
    $expense = Expense::factory()->for($wedding)->invoiced('1000.00')->create();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.payments.store', [$wedding, $expense]), [
        'amount' => '1200.00', 'paid_date' => '2026-11-10',
    ])->assertCreated();

    $this->getJson(route('v1.weddings.expenses.show', [$wedding, $expense]))
        ->assertOk()
        ->assertJsonPath('data.outstanding_amount', ['amount' => '0.00', 'currency' => 'LAK', 'basis' => 'actual'])
        ->assertJsonPath('data.is_overpaid', true)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonCount(1, 'data.payments');
});

it('rejects statuses derived from payments', function (string $status) {
    [$user, $wedding, $category] = expenseOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.store', $wedding), [
        'category_id' => $category->id, 'description' => 'Hall', 'estimated_amount' => '100.00', 'status' => $status,
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
})->with(['partially_paid', 'paid']);

it('rejects a revenue category for an expense', function () {
    [$user, $wedding] = expenseOwner();
    $revenueCategory = Category::factory()->revenue()->for($wedding)->create();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.store', $wedding), [
        'category_id' => $revenueCategory->id, 'description' => 'Hall', 'estimated_amount' => '100.00',
    ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

it('filters expenses by status and due date', function () {
    [$user, $wedding] = expenseOwner();
    Expense::factory()->for($wedding)->booked()->create(['due_date' => '2026-12-01']);
    Expense::factory()->for($wedding)->booked()->create(['due_date' => '2027-03-01']);
    Expense::factory()->for($wedding)->create(['due_date' => '2026-12-05']);

    $this->actingAs($user)->getJson(route('v1.weddings.expenses.index', [
        'wedding' => $wedding, 'status' => 'booked', 'date_to' => '2026-12-31', 'sort' => 'due_date',
    ]))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.due_date', '2026-12-01');
});

it('soft deletes an expense', function () {
    [$user, $wedding] = expenseOwner();
    $expense = Expense::factory()->for($wedding)->create();

    $this->actingAs($user)->deleteJson(route('v1.weddings.expenses.destroy', [$wedding, $expense]))->assertNoContent();

    expect($expense->refresh()->trashed())->toBeTrue();
});

it('updates an expense at its snapshotted rate, ignoring client-sent rates and base amounts', function () {
    [$user, $wedding] = expenseOwner();
    // A newer rate must not leak into an existing record whose currency did not change.
    ExchangeRate::factory()->for($wedding)->create(['rate' => '23000.00000000', 'effective_date' => '2026-11-01']);
    $expense = Expense::factory()->for($wedding)->inCurrency(Currency::USD, '21850.00000000', '6500.00')->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.expenses.update', [$wedding, $expense]), [
        'actual_amount' => '6800.00', 'exchange_rate' => '1', 'base_actual_amount' => '1.00',
    ])->assertOk()
        ->assertJsonPath('data.actual_amount', ['amount' => '6800.00', 'currency' => 'USD'])
        ->assertJsonPath('data.exchange_rate', '21850.00000000')
        ->assertJsonPath('data.base_actual_amount', ['amount' => '148580000.00', 'currency' => 'LAK']);

    expect($expense->refresh())
        ->exchange_rate->toBe('21850.00000000')
        ->base_actual_amount->toBe('148580000.00');
});

it('rejects a status derived from payments on update with 422', function () {
    [$user, $wedding] = expenseOwner();
    $expense = Expense::factory()->for($wedding)->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.expenses.update', [$wedding, $expense]), ['status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Planned);
});
