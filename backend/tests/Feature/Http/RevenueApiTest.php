<?php

use App\Enums\Currency;
use App\Enums\RevenueStatus;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;

/**
 * @return array{0: User, 1: Wedding, 2: Category}
 */
function revenueOwner(): array
{
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);

    return [$user, $wedding, Category::factory()->revenue()->for($wedding)->create()];
}

it('creates revenue with a server-computed snapshot, ignoring client-sent rates', function () {
    [$user, $wedding, $category] = revenueOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.revenues.store', $wedding), [
        'category_id' => $category->id, 'source' => 'Savings', 'amount' => '3000.00', 'currency' => 'USD',
        'status' => 'received', 'received_date' => '2026-10-05',
        'exchange_rate' => '1', 'base_amount' => '1.00',
    ])->assertCreated()
        ->assertJsonPath('data.amount', ['amount' => '3000.00', 'currency' => 'USD'])
        ->assertJsonPath('data.exchange_rate', '21850.00000000')
        ->assertJsonPath('data.base_amount', ['amount' => '65550000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.category.id', $category->id);
});

it('rejects a category from another user\'s wedding', function () {
    [$user, $wedding] = revenueOwner();
    $foreign = Category::factory()->revenue()->create();

    $this->actingAs($user)->postJson(route('v1.weddings.revenues.store', $wedding), [
        'category_id' => $foreign->id, 'source' => 'Gift', 'amount' => '100.00', 'currency' => 'LAK',
    ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

it('names the pair and date when no rate exists yet', function () {
    [$user, $wedding, $category] = revenueOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.revenues.store', $wedding), [
        'category_id' => $category->id, 'source' => 'Gift', 'amount' => '100.00', 'currency' => 'THB', 'expected_date' => '2026-12-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['currency' => 'No THB to LAK exchange rate is set on or before 2026-12-01.']);
});

it('requires received_date for received revenue', function () {
    [$user, $wedding, $category] = revenueOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.revenues.store', $wedding), [
        'category_id' => $category->id, 'source' => 'Gift', 'amount' => '100.00', 'status' => 'received',
    ])->assertUnprocessable()->assertJsonValidationErrors('received_date');
});

it('filters, sorts and paginates revenue', function () {
    [$user, $wedding, $category] = revenueOwner();
    Revenue::factory()->for($wedding)->recycle($category)->create(['category_id' => $category->id, 'amount' => '100.00', 'expected_date' => '2026-11-10']);
    Revenue::factory()->for($wedding)->recycle($category)->create(['category_id' => $category->id, 'amount' => '300.00', 'expected_date' => '2026-11-20']);
    Revenue::factory()->for($wedding)->received()->create(['amount' => '999.00', 'received_date' => '2026-11-15']);
    Revenue::factory()->for($wedding)->inCurrency(Currency::USD, '21850', '50.00')->create(['expected_date' => '2026-11-12']);

    $this->actingAs($user)->getJson(route('v1.weddings.revenues.index', [
        'wedding' => $wedding, 'status' => 'expected', 'currency' => 'LAK',
        'date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'sort' => '-base_amount', 'per_page' => 1,
    ]))->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.amount.amount', '300.00');
});

it('rejects unknown sort keys', function () {
    [$user, $wedding] = revenueOwner();

    $this->actingAs($user)->getJson(route('v1.weddings.revenues.index', ['wedding' => $wedding, 'sort' => 'password']))
        ->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('updates and soft deletes revenue', function () {
    [$user, $wedding] = revenueOwner();
    $revenue = Revenue::factory()->for($wedding)->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.revenues.update', [$wedding, $revenue]), [
        'status' => 'received', 'received_date' => '2026-11-01',
    ])->assertOk()->assertJsonPath('data.status', RevenueStatus::Received->value);

    $this->deleteJson(route('v1.weddings.revenues.destroy', [$wedding, $revenue]))->assertNoContent();
    expect($revenue->refresh()->trashed())->toBeTrue();
});
