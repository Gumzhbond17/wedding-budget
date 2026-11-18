<?php

use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('turns a unique index race into the standard 422 validation body', function () {
    $rate = ExchangeRate::factory()->create();

    // Simulates the second of two concurrent requests: validation passed, the insert collides.
    Route::post('api/v1/_test/duplicate-rate', fn () => DB::table('exchange_rates')->insert(
        $rate->only(['wedding_id', 'from_currency', 'to_currency', 'rate']) + ['effective_date' => $rate->effective_date->toDateString()],
    ));

    $this->postJson('api/v1/_test/duplicate-rate')
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'A rate for this currency already exists on that date.',
            'errors' => ['from_currency' => ['A rate for this currency already exists on that date.']],
        ]);
});

it('falls back to a generic field for unknown unique indexes', function () {
    Route::post('api/v1/_test/duplicate-session', function () {
        DB::table('sessions')->insert(['id' => 'same', 'payload' => '', 'last_activity' => 0]);
        DB::table('sessions')->insert(['id' => 'same', 'payload' => '', 'last_activity' => 0]);
    });

    $this->postJson('api/v1/_test/duplicate-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['record' => 'This record already exists.']);
});

it('returns the same 404 body for an unknown route', function () {
    $this->getJson('api/v1/does-not-exist')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
});

it('rate limits writes per user', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 60) as $request) {
        $this->postJson(route('v1.weddings.store'), [])->assertUnprocessable();
    }

    $this->postJson(route('v1.weddings.store'), [])->assertTooManyRequests();
});

it('does not rate limit reads at the write limit', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    $this->actingAs($user);

    foreach (range(1, 61) as $request) {
        $this->getJson(route('v1.weddings.show', $wedding))->assertOk();
    }
});
