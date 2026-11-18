<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| Every wedding-scoped endpoint, attacked with another user's ids at each level of the URL:
| a foreign wedding, then the user's own wedding with a foreign child, and so on down.
| All must answer the same 404 body as a missing id, before any validation runs, and leave
| the other user's data untouched.
*/

/** @return list<array{0: string, 1: string, 2: list<string>}> [method, route name, URL parameters] */
function weddingScopedEndpoints(): array
{
    $wedding = ['wedding'];
    $expense = ['wedding', 'expense'];
    $payment = ['wedding', 'expense', 'payment'];

    return [
        ['GET', 'v1.weddings.show', $wedding],
        ['PUT', 'v1.weddings.update', $wedding],
        ['DELETE', 'v1.weddings.destroy', $wedding],
        ['GET', 'v1.weddings.dashboard', $wedding],
        ['GET', 'v1.weddings.exports.revenues', $wedding],
        ['GET', 'v1.weddings.exports.expenses', $wedding],
        ['GET', 'v1.weddings.categories.index', $wedding],
        ['POST', 'v1.weddings.categories.store', $wedding],
        ['PUT', 'v1.weddings.categories.update', ['wedding', 'category']],
        ['DELETE', 'v1.weddings.categories.destroy', ['wedding', 'category']],
        ['GET', 'v1.weddings.exchange-rates.index', $wedding],
        ['POST', 'v1.weddings.exchange-rates.store', $wedding],
        ['PUT', 'v1.weddings.exchange-rates.update', ['wedding', 'exchangeRate']],
        ['DELETE', 'v1.weddings.exchange-rates.destroy', ['wedding', 'exchangeRate']],
        ['GET', 'v1.weddings.revenues.index', $wedding],
        ['POST', 'v1.weddings.revenues.store', $wedding],
        ['GET', 'v1.weddings.revenues.show', ['wedding', 'revenue']],
        ['PUT', 'v1.weddings.revenues.update', ['wedding', 'revenue']],
        ['DELETE', 'v1.weddings.revenues.destroy', ['wedding', 'revenue']],
        ['GET', 'v1.weddings.expenses.index', $wedding],
        ['POST', 'v1.weddings.expenses.store', $wedding],
        ['GET', 'v1.weddings.expenses.show', $expense],
        ['PUT', 'v1.weddings.expenses.update', $expense],
        ['DELETE', 'v1.weddings.expenses.destroy', $expense],
        ['GET', 'v1.weddings.expenses.payments.index', $expense],
        ['POST', 'v1.weddings.expenses.payments.store', $expense],
        ['PUT', 'v1.weddings.expenses.payments.update', $payment],
        ['DELETE', 'v1.weddings.expenses.payments.destroy', $payment],
        ['GET', 'v1.weddings.expenses.payments.receipt.show', $payment],
        ['POST', 'v1.weddings.expenses.payments.receipt.store', $payment],
        ['DELETE', 'v1.weddings.expenses.payments.receipt.destroy', $payment],
    ];
}

dataset('foreign references', function () {
    foreach (weddingScopedEndpoints() as [$method, $route, $parameters]) {
        // Parameters before $from are the user's own; from $from onwards they are the attacker's target.
        foreach (array_keys($parameters) as $from) {
            $foreign = implode(', ', array_slice($parameters, $from));
            yield "{$method} {$route} with foreign {$foreign}" => [$method, $route, $parameters, $from];
        }
    }
});

dataset('endpoints', function () {
    foreach (weddingScopedEndpoints() as [$method, $route, $parameters]) {
        yield "{$method} {$route}" => [$method, $route, $parameters];
    }
});

it('answers 404 for another user\'s resources', function (string $method, string $route, array $parameters, int $from) {
    Storage::fake('receipts');
    $mine = weddingGraphFor($me = User::factory()->create());
    $theirs = weddingGraphFor(User::factory()->create());
    $before = weddingRowCounts($theirs['wedding']->id);
    $theirPayment = $theirs['payment']->only(['amount', 'receipt_path']);

    $url = route($route, collect($parameters)
        ->mapWithKeys(fn (string $name, int $i) => [$name => ($i < $from ? $mine : $theirs)[$name]])
        ->all());

    $this->actingAs($me);

    // An invalid body proves authorization runs before validation (a 422 would confirm the id).
    $this->json($method, $url, ['name' => '', 'amount' => 'invalid'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);

    expect(weddingRowCounts($theirs['wedding']->id))->toBe($before)
        ->and($theirs['payment']->refresh()->only(['amount', 'receipt_path']))->toBe($theirPayment);
})->with('foreign references');

it('gives a missing id the same 404 body as a foreign one', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('v1.weddings.show', ['wedding' => 999_999]))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
});

it('requires authentication on every endpoint', function (string $method, string $route, array $parameters) {
    Storage::fake('receipts');
    $mine = weddingGraphFor(User::factory()->create());

    $this->json($method, route($route, collect($parameters)->mapWithKeys(fn (string $name) => [$name => $mine[$name]])->all()))
        ->assertUnauthorized();
})->with('endpoints');

it('requires authentication to list and create weddings', function (string $method) {
    $this->json($method, route('v1.weddings.index'))->assertUnauthorized();
})->with(['GET', 'POST']);
