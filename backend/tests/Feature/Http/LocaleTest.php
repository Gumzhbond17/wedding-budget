<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Models\Wedding;

/*
 * The SPA sends its language as Accept-Language. No Lao strings exist yet, so each test adds
 * the lines it needs; marker text proves which locale answered.
 */
beforeEach(function () {
    app('translator')->addLines(['validation.required' => 'LO :attribute required'], 'lo');

    // Application messages use the English sentence as key, i.e. a future lang/lo.json.
    // addLines() cannot take keys containing dots, so a temporary JSON file stands in.
    $this->jsonPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lang-'.uniqid();
    mkdir($this->jsonPath);
    file_put_contents($this->jsonPath.DIRECTORY_SEPARATOR.'lo.json', json_encode([
        'Not found.' => 'LO not found',
        'Payments must be in the expense currency (:currency).' => 'LO currency :currency',
    ]));
    app('translator')->addJsonPath($this->jsonPath);
});

afterEach(function () {
    unlink($this->jsonPath.DIRECTORY_SEPARATOR.'lo.json');
    rmdir($this->jsonPath);
});

it('answers in the requested supported language', function (string $header) {
    $this->postJson(route('v1.auth.login'), [], ['Accept-Language' => $header])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'LO password required')
        ->assertHeader('Vary');
})->with([
    'plain' => 'lo',
    'with region' => 'lo-LA',
    'preferred by q-value' => 'en;q=0.5, lo;q=0.9',
]);

it('falls back to English for a missing, unsupported or lower-ranked language', function (?string $header) {
    $headers = $header === null ? [] : ['Accept-Language' => $header];

    $this->postJson(route('v1.auth.login'), [], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'The password field is required.');
})->with([
    'no header' => null,
    'unsupported' => 'fr-FR',
    'Lao ranked lower' => 'en, lo;q=0.4',
    'not a locale' => '../../etc',
]);

it('falls back to English per key while a Lao string is missing', function () {
    $this->postJson(route('v1.auth.login'), ['email' => 'not-an-email', 'password' => 'x'], ['Accept-Language' => 'lo'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');
});

it('translates application messages, including the 404 for an unknown route', function () {
    $this->getJson('/api/v1/no-such-route', ['Accept-Language' => 'lo'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'LO not found']);
});

it('passes placeholders through translated application messages', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    $expense = Expense::factory()->for($wedding)->state(['category_id' => Category::factory()->for($wedding)])->create();

    $this->actingAs($user)->postJson(
        route('v1.weddings.expenses.payments.store', [$wedding, $expense]),
        ['amount' => '100.00', 'paid_date' => '2026-11-10', 'currency' => 'USD'],
        ['Accept-Language' => 'lo'],
    )->assertUnprocessable()->assertJsonPath('errors.currency.0', 'LO currency LAK');
});

it('lists Accept-Language in Vary so caches keep languages apart', function () {
    $vary = $this->getJson('/up')->headers->get('Vary');

    expect($vary)->toContain('Accept-Language');
});
