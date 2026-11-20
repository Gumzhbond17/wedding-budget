<?php

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\GiftEnvelope;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;

/*
 * Every hand-written validation message, asserted as the text the user reads through the
 * API. Several live in actions; the Form Requests run first, so a message only an action
 * knows would be replaced by Laravel's generic text ("The selected status is invalid.")
 * unless the Form Request shares it. These tests catch that.
 */

beforeEach(function () {
    $this->travelTo('2026-11-18');
    $this->user = User::factory()->create();
    $this->wedding = Wedding::factory()->for($this->user)->create();
    $this->expenseCategory = Category::factory()->expense()->for($this->wedding)->create();
    $this->revenueCategory = Category::factory()->revenue()->for($this->wedding)->create();
    $this->actingAs($this->user);
});

function expenseInput(array $overrides = []): array
{
    return ['category_id' => test()->expenseCategory->id, 'description' => 'Hall', 'estimated_amount' => '1000', ...$overrides];
}

function revenueInput(array $overrides = []): array
{
    return ['category_id' => test()->revenueCategory->id, 'source' => 'Parents', 'amount' => '1000', ...$overrides];
}

describe('expense status', function () {
    $message = 'Status can be planned, booked or cancelled. Partially paid and paid follow from payments.';

    it('explains derived statuses on create', function (string $status) use ($message) {
        $this->postJson(route('v1.weddings.expenses.store', $this->wedding), expenseInput(['status' => $status]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => $message]);
    })->with(['partially_paid', 'paid', 'unknown']);

    it('explains derived statuses on update', function () use ($message) {
        $expense = Expense::factory()->for($this->wedding)->create();

        $this->patchJson(route('v1.weddings.expenses.update', [$this->wedding, $expense]), ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => $message]);
    });
});

describe('revenue received date', function () {
    $message = 'A received revenue needs the date it was received.';

    it('is required when creating received revenue', function () use ($message) {
        $this->postJson(route('v1.weddings.revenues.store', $this->wedding), revenueInput(['status' => 'received']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['received_date' => $message]);
    });

    it('is required when marking revenue as received', function () use ($message) {
        $revenue = Revenue::factory()->for($this->wedding)->create();

        $this->patchJson(route('v1.weddings.revenues.update', [$this->wedding, $revenue]), ['status' => 'received'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['received_date' => $message]);
    });
});

it('names the expected category type', function () {
    $this->postJson(route('v1.weddings.expenses.store', $this->wedding), expenseInput(['category_id' => $this->revenueCategory->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id' => "The selected category is not one of this wedding's expense categories."]);

    $this->postJson(route('v1.weddings.revenues.store', $this->wedding), revenueInput(['category_id' => $this->expenseCategory->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id' => "The selected category is not one of this wedding's revenue categories."]);
});

it('names the missing rate and where to add it', function () {
    $this->postJson(route('v1.weddings.expenses.store', $this->wedding), expenseInput(['currency' => 'USD']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency' => 'No USD to LAK exchange rate is set on or before 2026-11-18. Add one in Settings first.']);
});

it('keeps the expense currency once payments exist', function () {
    ExchangeRate::factory()->for($this->wedding)->create(['effective_date' => '2026-11-01']);
    $expense = Expense::factory()->for($this->wedding)->create();
    ExpensePayment::factory()->for($expense)->create(['paid_date' => '2026-11-10']);

    $this->patchJson(route('v1.weddings.expenses.update', [$this->wedding, $expense]), ['currency' => 'USD'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency' => 'The currency cannot change once payments are recorded.']);
});

it('keeps payments in the expense currency', function () {
    $expense = Expense::factory()->for($this->wedding)->create();

    $this->postJson(route('v1.weddings.expenses.payments.store', [$this->wedding, $expense]), ['amount' => '100', 'paid_date' => '2026-11-10', 'currency' => 'THB'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency' => 'Payments must be in the expense currency (LAK).']);
});

it('explains why the base currency cannot change', function (Closure $arrange, string $message) {
    $arrange($this->wedding);

    $this->patchJson(route('v1.weddings.update', $this->wedding), ['base_currency' => 'USD'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_currency' => $message]);
})->with([
    'records exist' => [fn (Wedding $wedding) => Revenue::factory()->for($wedding)->create(), 'The base currency cannot change once revenues, expenses or gift envelopes exist (including deleted ones).'],
    // Envelope amounts are stored in the base currency, so one trashed envelope is enough to lock it.
    'gift envelopes exist' => [fn (Wedding $wedding) => GiftEnvelope::factory()->for($wedding)->create()->delete(), 'The base currency cannot change once revenues, expenses or gift envelopes exist (including deleted ones).'],
    'rates exist' => [fn (Wedding $wedding) => ExchangeRate::factory()->for($wedding)->create(), 'Delete the exchange rates first: they convert into the current base currency.'],
]);

it('describes a malformed or non-positive amount', function () {
    $this->postJson(route('v1.weddings.expenses.store', $this->wedding), expenseInput(['estimated_amount' => '1,000.00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estimated_amount' => 'The estimated amount must be an amount with at most 18 digits and 2 decimals.']);

    $this->postJson(route('v1.weddings.revenues.store', $this->wedding), revenueInput(['amount' => '0.00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount' => 'The amount must be greater than zero.']);
});

it('explains a category that is still in use', function () {
    Expense::factory()->for($this->wedding)->create(['category_id' => $this->expenseCategory->id]);

    $this->deleteJson(route('v1.weddings.categories.destroy', [$this->wedding, $this->expenseCategory]))
        ->assertConflict()
        ->assertJsonPath('message', 'This category is still used by revenue or expense records, including deleted ones. Move them to another category first.');
});
