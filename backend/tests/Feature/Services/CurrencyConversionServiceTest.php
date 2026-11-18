<?php

use App\Enums\Currency;
use App\Exceptions\MissingExchangeRateException;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use App\Services\CurrencyConversionService;
use Illuminate\Support\Carbon;

it('converts and rounds half away from zero to 2 decimals', function (string $amount, string $rate, string $expected) {
    expect(app(CurrencyConversionService::class)->convert($amount, $rate))->toBe($expected);
})->with([
    'exactly half rounds up' => ['1.00', '0.00500000', '0.01'],
    'just below half rounds down' => ['1.00', '0.00499999', '0.00'],
    'repeating fraction' => ['33.33', '0.33333333', '11.11'],
    'THB to LAK' => ['35000.00', '628.40000000', '21994000.00'],
    'USD to LAK' => ['2000.00', '21650.00000000', '43300000.00'],
    'base currency' => ['150000000.00', '1', '150000000.00'],
    'large LAK value stays exact' => ['999999999999.99', '21850.12345678', '21850123456779781.50'],
]);

it('uses 1 for the base currency', function () {
    $wedding = Wedding::factory()->create();

    expect((string) app(CurrencyConversionService::class)->rateToBase($wedding, Currency::LAK, Carbon::parse('2026-11-18')))->toBe('1');
});

it('uses the latest rate effective on or before the date', function (string $date, string $expected) {
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '612.50000000', 'effective_date' => '2026-08-01']);
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '628.40000000', 'effective_date' => '2026-10-01']);

    expect((string) app(CurrencyConversionService::class)->rateToBase($wedding, Currency::THB, Carbon::parse($date)))->toBe($expected);
})->with([
    'on the first effective day' => ['2026-08-01', '612.50000000'],
    'between two rates' => ['2026-09-30', '612.50000000'],
    'after the newer rate' => ['2026-11-18', '628.40000000'],
]);

it('never uses another wedding\'s rates', function () {
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->create(['effective_date' => '2026-01-01']);

    expect(fn () => app(CurrencyConversionService::class)->rateToBase($wedding, Currency::USD, Carbon::parse('2026-11-18')))
        ->toThrow(MissingExchangeRateException::class, 'No USD to LAK exchange rate is set on or before 2026-11-18.');
});

it('renders a missing rate as a 422 JSON error', function () {
    $response = (new MissingExchangeRateException(Currency::USD, Currency::LAK, Carbon::parse('2026-11-18')))->render();

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toMatchArray(['code' => 'exchange_rate_missing']);
});
