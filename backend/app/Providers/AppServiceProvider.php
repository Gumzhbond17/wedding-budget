<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        $this->configureRateLimiting();

        Password::defaults(fn () => Password::min(8)->letters()->numbers());
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

    private function configureRateLimiting(): void
    {
        // Login and register: strict, per email (password guessing against one account) and
        // per IP (spraying many accounts or mass registration).
        RateLimiter::for('auth', fn (Request $request): array => [
            Limit::perMinute(5)->by('auth-email:'.Str::lower((string) $request->input('email'))),
            Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
        ]);

        // Writes only; reads are unlimited so dashboards and lists stay responsive.
        RateLimiter::for('writes', fn (Request $request): Limit => $request->isMethodSafe()
            ? Limit::none()
            : Limit::perMinute(60)->by('writes:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('receipts', fn (Request $request): Limit => Limit::perMinute(30)->by('receipts:'.$request->ip()));
    }
}
