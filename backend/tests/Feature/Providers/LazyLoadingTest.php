<?php

use App\Models\Expense;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;

afterEach(function () {
    // Model strictness is static: restore the non-production setup for the following tests.
    app()->detectEnvironment(fn () => 'testing');
    Model::handleLazyLoadingViolationUsing(null);
    Model::shouldBeStrict();
});

it('throws on lazy loading outside production', function () {
    Expense::factory()->count(2)->create();

    expect(fn () => Expense::all()->first()->category)->toThrow(LazyLoadingViolationException::class);
});

it('logs lazy loading in production instead of failing the request', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();
    Log::spy();
    Expense::factory()->count(2)->create();

    $category = Expense::all()->first()->category;

    expect($category)->not->toBeNull();
    Log::shouldHaveReceived('warning')->once()->with('Lazy loading violation', [
        'model' => Expense::class,
        'relation' => 'category',
    ]);
});
