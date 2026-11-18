<?php

use App\Actions\ExchangeRates\ManageExchangeRate;
use App\Actions\Revenues\ManageRevenue;
use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 */
function createRate(Wedding $wedding, array $overrides = []): ExchangeRate
{
    return app(ManageExchangeRate::class)->create($wedding, [
        'from_currency' => 'USD', 'rate' => '21850.00000000', 'effective_date' => '2026-10-01', ...$overrides,
    ]);
}

it('stores a foreign to base rate', function () {
    $wedding = Wedding::factory()->create();

    $rate = createRate($wedding, ['to_currency' => 'LAK']);

    expect($rate->refresh())
        ->from_currency->toBe(Currency::USD)
        ->to_currency->toBe(Currency::LAK)
        ->rate->toBe('21850.00000000')
        ->effective_date->toDateString()->toBe('2026-10-01');
});

it('defaults to_currency to the base currency', function () {
    $wedding = Wedding::factory()->create(['base_currency' => Currency::USD]);

    expect(createRate($wedding, ['from_currency' => 'THB', 'rate' => '0.02860000'])->to_currency)->toBe(Currency::USD);
});

it('rejects invalid rates', function (array $input, string $field) {
    $wedding = Wedding::factory()->create();

    expect(fn () => createRate($wedding, $input))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($field))
        ->and($wedding->exchangeRates()->count())->toBe(0);
})->with([
    'to a currency other than base' => [['to_currency' => 'THB'], 'to_currency'],
    'from the base currency' => [['from_currency' => 'LAK'], 'from_currency'],
    'unknown currency' => [['from_currency' => 'EUR'], 'from_currency'],
    'zero rate' => [['rate' => '0'], 'rate'],
    'negative rate' => [['rate' => '-21850'], 'rate'],
    'more than 8 decimals' => [['rate' => '1.123456789'], 'rate'],
    'more than 12 integer digits' => [['rate' => '1234567890123'], 'rate'],
    'not a number' => [['rate' => 'abc'], 'rate'],
    'malformed date' => [['effective_date' => '01/10/2026'], 'effective_date'],
]);

it('rejects a second rate for the same pair and date', function () {
    $wedding = Wedding::factory()->create();
    createRate($wedding);

    expect(fn () => createRate($wedding, ['rate' => '22000']))
        ->toThrow(ValidationException::class, 'A rate for this currency already exists on that date.');
});

it('allows the same pair and date in another wedding', function () {
    createRate(Wedding::factory()->create());

    expect(createRate(Wedding::factory()->create())->exists)->toBeTrue();
});

it('updates a rate without tripping over its own unique key', function () {
    $rate = createRate(Wedding::factory()->create());

    app(ManageExchangeRate::class)->update($rate, ['rate' => '21900.5']);

    expect($rate->refresh()->rate)->toBe('21900.50000000');
});

it('leaves existing records untouched when a rate is edited or deleted', function () {
    $wedding = Wedding::factory()->create();
    $rate = createRate($wedding);
    $revenue = app(ManageRevenue::class)->create($wedding, [
        'category_id' => Category::factory()->revenue()->for($wedding)->create()->id,
        'source' => 'Savings', 'amount' => '1000.00', 'currency' => 'USD', 'received_date' => '2026-10-05', 'status' => 'received',
    ]);

    app(ManageExchangeRate::class)->update($rate, ['rate' => '30000']);
    expect($revenue->refresh())->exchange_rate->toBe('21850.00000000')->base_amount->toBe('21850000.00');

    app(ManageExchangeRate::class)->delete($rate);
    expect($revenue->refresh())->exchange_rate->toBe('21850.00000000')->base_amount->toBe('21850000.00');
});
