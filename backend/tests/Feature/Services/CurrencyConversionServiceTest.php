<?php

use App\Contracts\ExchangeRateProvider;
use App\Enums\Currency;
use App\Exceptions\MissingExchangeRateException;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use App\Services\CurrencyConversionService;
use Illuminate\Support\Carbon;

/**
 * A LAK wedding with THB rates effective 2026-08-01 (612.50) and 2026-10-01 (628.40).
 */
function weddingWithThbRates(): Wedding
{
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '612.50000000', 'effective_date' => '2026-08-01']);
    ExchangeRate::factory()->for($wedding)->thbToLak()->create(['rate' => '628.40000000', 'effective_date' => '2026-10-01']);

    return $wedding;
}

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

it('uses rate 1 for the base currency without a lookup', function () {
    $this->mock(ExchangeRateProvider::class)->shouldNotReceive('rate');
    $wedding = Wedding::factory()->create();

    expect((string) app(CurrencyConversionService::class)->rateToBase($wedding, Currency::LAK, Carbon::parse('2026-11-18')))->toBe('1');
});

it('uses the latest rate effective on or before the date', function (string $date, string $expected) {
    $wedding = weddingWithThbRates();

    expect((string) app(CurrencyConversionService::class)->rateToBase($wedding, Currency::THB, Carbon::parse($date)))->toBe($expected);
})->with([
    'on the first effective day' => ['2026-08-01', '612.50000000'],
    'between two rates' => ['2026-09-30', '612.50000000'],
    'after the newer rate' => ['2026-11-18', '628.40000000'],
]);

it('fails with a 422 naming the pair and date instead of using a later rate', function () {
    $wedding = weddingWithThbRates();

    try {
        app(CurrencyConversionService::class)->rateToBase($wedding, Currency::THB, Carbon::parse('2026-07-31'));
        $this->fail('Expected a missing rate error.');
    } catch (MissingExchangeRateException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errors())->toBe([
                'currency' => ['No THB to LAK exchange rate is set on or before 2026-07-31. Add one in Settings first.'],
            ]);
    }
});

it('never uses another wedding\'s rates', function () {
    $wedding = Wedding::factory()->create();
    ExchangeRate::factory()->create(['effective_date' => '2026-01-01']);

    expect(fn () => app(CurrencyConversionService::class)->rateToBase($wedding, Currency::USD, Carbon::parse('2026-11-18')))
        ->toThrow(MissingExchangeRateException::class, 'No USD to LAK exchange rate is set on or before 2026-11-18.');
});
