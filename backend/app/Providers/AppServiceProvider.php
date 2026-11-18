<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureEloquentStrictness();
    }

    /**
     * Outside production, fail loudly on lazy loading (N+1), on silently discarded non-fillable
     * attributes (e.g. a client-sent exchange_rate) and on missing attributes.
     *
     * In production a request never breaks over these, but lazy loading is still detected and
     * logged, so an N+1 that slipped past the tests shows up in the logs instead of only as latency.
     */
    private function configureEloquentStrictness(): void
    {
        if (! $this->app->isProduction()) {
            Model::shouldBeStrict();

            return;
        }

        Model::shouldBeStrict(false);
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Lazy loading violation', [
                'model' => $model::class,
                'relation' => $relation,
            ]);
        });
    }
}
