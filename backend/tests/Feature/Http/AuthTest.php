<?php

use App\Models\User;

/*
| SPA requests carry the frontend Origin, which makes Sanctum treat them as stateful
| (session cookie). CSRF verification is skipped by Laravel while running tests.
*/

beforeEach(fn () => $this->withHeader('Origin', 'http://localhost:3000'));

it('registers a user and starts a session', function () {
    $this->postJson(route('v1.auth.register'), [
        'name' => 'Noy', 'email' => 'noy@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertCreated()->assertJsonPath('data.email', 'noy@example.com');

    $this->assertAuthenticated('web');
    $this->getJson(route('v1.auth.user'))->assertOk()->assertJsonPath('data.name', 'Noy');
});

it('validates registration', function (array $input, string $field) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson(route('v1.auth.register'), [
        'name' => 'Noy', 'email' => 'noy@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'taken email' => [['email' => 'taken@example.com'], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'abc1', 'password_confirmation' => 'abc1'], 'password'],
    'password without numbers' => [['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different1'], 'password'],
]);

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->postJson(route('v1.auth.login'), ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects wrong credentials with the same message for unknown emails', function (string $email) {
    User::factory()->create(['email' => 'vanh@example.com']);

    $this->postJson(route('v1.auth.login'), ['email' => $email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);

    $this->assertGuest('web');
})->with(['known email' => 'vanh@example.com', 'unknown email' => 'nobody@example.com']);

it('throttles repeated login attempts for one email', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('v1.auth.login'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson(route('v1.auth.login'), ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson(route('v1.auth.logout'))->assertNoContent();

    $this->assertGuest('web');
});

it('rejects guests on the current user endpoint', function () {
    $this->getJson(route('v1.auth.user'))->assertUnauthorized();
});

it('answers guests 401 JSON even without an Accept header, never a login redirect', function () {
    // e.g. curl, a crawler or a health probe; there is no login route to redirect to.
    $this->get(route('v1.auth.user'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('throttles registration attempts from one IP', function () {
    foreach (range(1, 20) as $attempt) {
        $this->postJson(route('v1.auth.register'), ['email' => "user{$attempt}@example.com"])->assertUnprocessable();
    }

    $this->postJson(route('v1.auth.register'), ['email' => 'user21@example.com'])->assertTooManyRequests();
});
