<?php

use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\Wedding;

function rateOwner(): array
{
    $user = User::factory()->create();

    return [$user, Wedding::factory()->for($user)->create()];
}

it('creates a foreign to base rate', function () {
    [$user, $wedding] = rateOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.exchange-rates.store', $wedding), [
        'from_currency' => 'THB', 'rate' => '628.4', 'effective_date' => '2026-10-01',
    ])->assertCreated()
        ->assertJsonPath('data.from_currency', 'THB')
        ->assertJsonPath('data.to_currency', 'LAK')
        ->assertJsonPath('data.rate', '628.40000000');
});

it('validates rates', function (array $input, string $field) {
    [$user, $wedding] = rateOwner();
    ExchangeRate::factory()->for($wedding)->create(['effective_date' => '2026-10-01']);

    $this->actingAs($user)->postJson(route('v1.weddings.exchange-rates.store', $wedding), [
        'from_currency' => 'THB', 'rate' => '628.4', 'effective_date' => '2026-10-01', ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'not into the base currency' => [['to_currency' => 'USD'], 'to_currency'],
    'from the base currency' => [['from_currency' => 'LAK'], 'from_currency'],
    'zero rate' => [['rate' => '0'], 'rate'],
    'duplicate pair and date' => [['from_currency' => 'USD'], 'from_currency'],
]);

it('updates and deletes a rate', function () {
    [$user, $wedding] = rateOwner();
    $rate = ExchangeRate::factory()->for($wedding)->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.exchange-rates.update', [$wedding, $rate]), ['rate' => '22000'])
        ->assertOk()->assertJsonPath('data.rate', '22000.00000000');
    $this->deleteJson(route('v1.weddings.exchange-rates.destroy', [$wedding, $rate]))->assertNoContent();
});

it('lists only the wedding\'s own rates, newest effective date first', function () {
    [$user, $wedding] = rateOwner();
    $october = ExchangeRate::factory()->for($wedding)->create(['effective_date' => '2026-10-01']);
    $november = ExchangeRate::factory()->for($wedding)->create(['effective_date' => '2026-11-01']);
    ExchangeRate::factory()->create(['effective_date' => '2026-12-01']);

    $response = $this->actingAs($user)->getJson(route('v1.weddings.exchange-rates.index', $wedding))->assertOk();

    expect($response->json('data.*.id'))->toBe([$november->id, $october->id]);
});
