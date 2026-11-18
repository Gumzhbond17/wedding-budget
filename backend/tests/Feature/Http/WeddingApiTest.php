<?php

use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;

it('lists only the user\'s own weddings', function () {
    $user = User::factory()->create();
    $mine = Wedding::factory()->for($user)->create();
    Wedding::factory()->create();

    $this->actingAs($user)->getJson(route('v1.weddings.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('meta.total', 1);
});

it('creates a wedding with its default categories', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('v1.weddings.store'), [
        'name' => 'Vanh & Noy', 'wedding_date' => '2027-02-20', 'budget_target' => '350000000.00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.base_currency', 'LAK')
        ->assertJsonPath('data.status', 'planning')
        ->assertJsonPath('data.budget_target', ['amount' => '350000000.00', 'currency' => 'LAK']);
    expect(Wedding::query()->findOrFail($response->json('data.id'))->categories()->count())->toBe(18);
});

it('validates wedding input', function (array $input, string $field) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('v1.weddings.store'), ['name' => 'Ours', ...$input])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown currency' => [['base_currency' => 'EUR'], 'base_currency'],
    'negative budget' => [['budget_target' => '-1'], 'budget_target'],
    'three decimals' => [['budget_target' => '1.005'], 'budget_target'],
    'float budget' => [['budget_target' => 1500.5], 'budget_target'],
    'malformed date' => [['wedding_date' => '20/02/2027'], 'wedding_date'],
]);

it('refuses to change the base currency once records exist', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    Revenue::factory()->for($wedding)->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.update', $wedding), ['base_currency' => 'USD'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('base_currency');
});

it('deletes a wedding with its records', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    Revenue::factory()->for($wedding)->create();

    $this->actingAs($user)->deleteJson(route('v1.weddings.destroy', $wedding))->assertNoContent();

    expect(Wedding::query()->whereKey($wedding->id)->exists())->toBeFalse();
});
