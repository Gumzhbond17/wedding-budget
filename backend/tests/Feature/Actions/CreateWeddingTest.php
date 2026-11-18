<?php

use App\Actions\Weddings\CreateWedding;
use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\WeddingStatus;
use App\Models\User;

it('creates a wedding owned by the user with its own default categories', function () {
    $user = User::factory()->create();

    $wedding = app(CreateWedding::class)->handle($user, ['name' => 'Vanh & Noy']);

    expect($wedding->user_id)->toBe($user->id)
        ->and($wedding->base_currency)->toBe(Currency::LAK)
        ->and($wedding->status)->toBe(WeddingStatus::Planning)
        ->and($wedding->categories()->ofType(CategoryType::Revenue)->pluck('name')->all())
        ->toEqualCanonicalizing(CategoryType::Revenue->defaultNames())
        ->and($wedding->categories()->ofType(CategoryType::Expense)->pluck('name')->all())
        ->toEqualCanonicalizing(CategoryType::Expense->defaultNames());
});

it('gives every wedding separate copies of the defaults', function () {
    $user = User::factory()->create();
    $first = app(CreateWedding::class)->handle($user, ['name' => 'First']);
    $second = app(CreateWedding::class)->handle($user, ['name' => 'Second']);

    $first->categories()->where('name', 'Venue')->update(['name' => 'Hotel ballroom']);

    expect($second->categories()->where('name', 'Venue')->exists())->toBeTrue()
        ->and($first->categories()->pluck('id')->intersect($second->categories()->pluck('id')))->toBeEmpty();
});
