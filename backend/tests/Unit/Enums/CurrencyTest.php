<?php

use App\Enums\Currency;

it('describes how each currency is displayed', function (Currency $currency, string $symbol, int $decimals, string $locale) {
    expect($currency->symbol())->toBe($symbol)
        ->and($currency->displayDecimals())->toBe($decimals)
        ->and($currency->locale())->toBe($locale);
})->with([
    'LAK shows whole kip' => [Currency::LAK, '₭', 0, 'lo-LA'],
    'USD shows cents' => [Currency::USD, '$', 2, 'en-US'],
    'THB shows satang' => [Currency::THB, '฿', 2, 'th-TH'],
]);

it('defaults to LAK', function () {
    expect(Currency::DEFAULT)->toBe(Currency::LAK);
});
