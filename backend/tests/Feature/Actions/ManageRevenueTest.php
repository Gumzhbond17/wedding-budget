<?php

use App\Actions\Revenues\ManageRevenue;
use App\Enums\Currency;
use App\Enums\RevenueStatus;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

/**
 * A LAK wedding with USD rates effective 2026-08-01 (21,650) and 2026-10-01 (21,850).
 */
function weddingWithUsdRates(): Wedding
{
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21650.00000000', 'effective_date' => '2026-08-01']);
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);

    return $wedding;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createRevenue(Wedding $wedding, array $overrides = []): Revenue
{
    return app(ManageRevenue::class)->create($wedding, [
        'category_id' => Category::factory()->revenue()->for($wedding)->create()->id,
        'source' => 'Joint savings',
        'amount' => '1000.00',
        'currency' => 'USD',
        ...$overrides,
    ]);
}

it('snapshots the rate of the transaction date', function (array $dates, string $rate, string $base) {
    $this->travelTo('2026-09-15');

    $revenue = createRevenue(weddingWithUsdRates(), $dates);

    expect($revenue->refresh())->exchange_rate->toBe($rate)->base_amount->toBe($base);
})->with([
    'received date wins' => [['received_date' => '2026-10-05', 'expected_date' => '2026-08-15', 'status' => 'received'], '21850.00000000', '21850000.00'],
    'expected date when not received' => [['expected_date' => '2026-12-01'], '21850.00000000', '21850000.00'],
    'save date when no date is set' => [[], '21650.00000000', '21650000.00'],
]);

it('uses rate 1 for the base currency', function () {
    $revenue = createRevenue(Wedding::factory()->create(), ['currency' => 'LAK', 'amount' => '80000000.00']);

    expect($revenue->refresh())->exchange_rate->toBe('1.00000000')->base_amount->toBe('80000000.00');
});

it('uses the base currency when none is given', function () {
    $wedding = Wedding::factory()->create();

    $revenue = app(ManageRevenue::class)->create($wedding, [
        'category_id' => Category::factory()->revenue()->for($wedding)->create()->id,
        'source' => 'Parents',
        'amount' => '5000000.00',
    ]);

    expect($revenue->refresh())->currency->toBe(Currency::LAK)->exchange_rate->toBe('1.00000000');
});

it('fails with a 422 and saves nothing when no rate exists on or before the date', function () {
    $wedding = weddingWithUsdRates();

    expect(fn () => createRevenue($wedding, ['expected_date' => '2026-07-31']))
        ->toThrow(MissingExchangeRateException::class, 'No USD to LAK exchange rate is set on or before 2026-07-31.')
        ->and($wedding->revenues()->count())->toBe(0);
});

it('keeps the stored rate when only the amount changes', function () {
    $wedding = weddingWithUsdRates();
    $revenue = createRevenue($wedding, ['expected_date' => '2026-08-15']);
    $wedding->exchangeRates()->update(['rate' => '30000']);

    app(ManageRevenue::class)->update($revenue, ['amount' => '1500.00']);

    expect($revenue->refresh())->exchange_rate->toBe('21650.00000000')->base_amount->toBe('32475000.00');
});

it('takes a new snapshot when the transaction date or currency changes', function (array $change, string $rate, string $base) {
    $wedding = weddingWithUsdRates();
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '628.40000000', 'effective_date' => '2026-10-01']);
    $revenue = createRevenue($wedding, ['expected_date' => '2026-08-15']);

    app(ManageRevenue::class)->update($revenue, $change);

    expect($revenue->refresh())->exchange_rate->toBe($rate)->base_amount->toBe($base);
})->with([
    'received later' => [['status' => 'received', 'received_date' => '2026-10-20'], '21850.00000000', '21850000.00'],
    'expected date moved' => [['expected_date' => '2026-11-01'], '21850.00000000', '21850000.00'],
    'currency changed' => [['currency' => 'THB', 'amount' => '25000.00', 'expected_date' => '2026-10-15'], '628.40000000', '15710000.00'],
]);

it('re-snapshots at received_date when an expected revenue is received', function () {
    $wedding = weddingWithUsdRates();
    $revenue = createRevenue($wedding, ['expected_date' => '2026-08-15']);
    expect($revenue->refresh()->exchange_rate)->toBe('21650.00000000');

    app(ManageRevenue::class)->update($revenue, ['status' => 'received', 'received_date' => '2026-10-20']);

    expect($revenue->refresh())
        ->status->toBe(RevenueStatus::Received)
        ->exchange_rate->toBe('21850.00000000')
        ->base_amount->toBe('21850000.00');
});

it('requires a received date for received revenue', function () {
    $revenue = createRevenue(weddingWithUsdRates(), ['expected_date' => '2026-08-15']);

    expect(fn () => app(ManageRevenue::class)->update($revenue, ['status' => 'received']))
        ->toThrow(ValidationException::class, 'A received revenue needs the date it was received.');
});
