<?php

use App\Models\Category;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;

function categoryOwner(): array
{
    $user = User::factory()->create();

    return [$user, Wedding::factory()->for($user)->create()];
}

it('lists categories filtered by type', function () {
    [$user, $wedding] = categoryOwner();
    Category::factory()->for($wedding)->create(['name' => 'Venue']);
    Category::factory()->revenue()->for($wedding)->create(['name' => 'Gifts']);

    $this->actingAs($user)->getJson(route('v1.weddings.categories.index', ['wedding' => $wedding, 'type' => 'revenue']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Gifts');
});

it('creates an expense category with a planned budget in base currency', function () {
    [$user, $wedding] = categoryOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.categories.store', $wedding), [
        'type' => 'expense', 'name' => 'Henna night', 'planned_amount' => '5000000.00',
    ])->assertCreated()->assertJsonPath('data.planned_amount', ['amount' => '5000000.00', 'currency' => 'LAK']);
});

it('validates categories', function (array $input, string $field) {
    [$user, $wedding] = categoryOwner();
    Category::factory()->for($wedding)->create(['name' => 'Venue']);

    $this->actingAs($user)->postJson(route('v1.weddings.categories.store', $wedding), ['type' => 'expense', 'name' => 'New', ...$input])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'duplicate name' => [['name' => 'Venue'], 'name'],
    'planned amount on revenue category' => [['type' => 'revenue', 'planned_amount' => '100.00'], 'planned_amount'],
    'unknown type' => [['type' => 'loan'], 'type'],
]);

it('allows the same name for a different type', function () {
    [$user, $wedding] = categoryOwner();
    Category::factory()->for($wedding)->create(['name' => 'Other']);

    $this->actingAs($user)->postJson(route('v1.weddings.categories.store', $wedding), ['type' => 'revenue', 'name' => 'Other'])
        ->assertCreated();
});

it('renames a category but never changes its type', function () {
    [$user, $wedding] = categoryOwner();
    $category = Category::factory()->for($wedding)->create();

    $this->actingAs($user)->patchJson(route('v1.weddings.categories.update', [$wedding, $category]), ['name' => 'Hotel ballroom'])
        ->assertOk()->assertJsonPath('data.name', 'Hotel ballroom');
    $this->patchJson(route('v1.weddings.categories.update', [$wedding, $category]), ['type' => 'revenue'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('refuses to delete a category in use with 409', function () {
    [$user, $wedding] = categoryOwner();
    $revenue = Revenue::factory()->for($wedding)->create();

    $this->actingAs($user)->deleteJson(route('v1.weddings.categories.destroy', [$wedding, $revenue->category_id]))
        ->assertConflict()
        ->assertJsonPath('code', 'category_in_use');
    expect(Revenue::query()->whereKey($revenue->id)->exists())->toBeTrue();
});

it('deletes an unused category', function () {
    [$user, $wedding] = categoryOwner();
    $category = Category::factory()->for($wedding)->create();

    $this->actingAs($user)->deleteJson(route('v1.weddings.categories.destroy', [$wedding, $category]))->assertNoContent();
});
