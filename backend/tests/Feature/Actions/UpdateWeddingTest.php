<?php

use App\Actions\Weddings\UpdateWedding;
use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\GiftEnvelope;
use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

it('changes the base currency while nothing depends on it', function () {
    $wedding = Wedding::factory()->create();

    app(UpdateWedding::class)->handle($wedding, ['base_currency' => 'USD']);

    expect($wedding->refresh()->base_currency)->toBe(Currency::USD);
});

it('locks the base currency once records or rates exist', function (Closure $arrange, string $message) {
    $wedding = Wedding::factory()->create();
    $arrange($wedding);

    expect(fn () => app(UpdateWedding::class)->handle($wedding, ['base_currency' => 'USD']))
        ->toThrow(ValidationException::class, $message)
        ->and($wedding->refresh()->base_currency)->toBe(Currency::LAK);
})->with([
    'a revenue' => [fn (Wedding $w) => Revenue::factory()->for($w)->create(), 'cannot change once revenues, expenses or gift envelopes exist'],
    'a soft-deleted expense' => [fn (Wedding $w) => Expense::factory()->for($w)->create()->delete(), 'cannot change once revenues, expenses or gift envelopes exist'],
    'a gift envelope' => [fn (Wedding $w) => GiftEnvelope::factory()->for($w)->create(), 'cannot change once revenues, expenses or gift envelopes exist'],
    'an exchange rate' => [fn (Wedding $w) => ExchangeRate::factory()->for($w)->create(), 'Delete the exchange rates first'],
]);

it('updates other fields while the base currency is locked', function () {
    $wedding = Wedding::factory()->create();
    Revenue::factory()->for($wedding)->create();

    app(UpdateWedding::class)->handle($wedding, ['name' => 'Renamed', 'base_currency' => 'LAK']);

    expect($wedding->refresh()->name)->toBe('Renamed');
});
