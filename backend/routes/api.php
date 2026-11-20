<?php

use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ExpensePaymentController;
use App\Http\Controllers\Api\V1\ExpensePaymentReceiptController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\GiftEnvelopeController;
use App\Http\Controllers\Api\V1\ReceiptDownloadController;
use App\Http\Controllers\Api\V1\RevenueController;
use App\Http\Controllers\Api\V1\WeddingController;
use Illuminate\Support\Facades\Route;

/*
| Version 1 of the JSON API, served under /api/v1.
|
| Every wedding-scoped route uses scoped bindings (a child id must belong to its parent in the
| URL, otherwise 404) and a policy check in controller middleware (another user's wedding is
| 404 as well). Rate limiters are defined in AppServiceProvider.
*/

Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', RegisterController::class)->name('register');
            Route::post('login', [SessionController::class, 'store'])->name('login');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
            Route::get('user', CurrentUserController::class)->name('user');
        });
    });

    // The signature is the credential: issued only after the payment policy passed, valid for
    // a few minutes, bound to this payment id.
    Route::get('receipts/{payment}', ReceiptDownloadController::class)
        ->middleware(['signed', 'throttle:receipts'])
        ->name('receipts.download');

    Route::middleware(['auth:sanctum', 'throttle:writes'])->group(function () {
        Route::apiResource('weddings', WeddingController::class);

        Route::get('weddings/{wedding}/dashboard', DashboardController::class)->name('weddings.dashboard');

        // Exports scan every record of a wedding, so they get their own, tighter limit.
        Route::controller(ExportController::class)
            ->prefix('weddings/{wedding}/exports')
            ->name('weddings.exports.')
            ->middleware('throttle:exports')
            ->group(function () {
                Route::get('revenues', 'revenues')->name('revenues');
                Route::get('expenses', 'expenses')->name('expenses');
                Route::get('gift-envelopes', 'giftEnvelopes')->name('gift-envelopes');
            });

        Route::scopeBindings()->group(function () {
            Route::apiResource('weddings.categories', CategoryController::class)->except('show');
            Route::apiResource('weddings.exchange-rates', ExchangeRateController::class)
                ->except('show')
                ->parameters(['exchange-rates' => 'exchangeRate']);
            Route::apiResource('weddings.revenues', RevenueController::class);
            Route::apiResource('weddings.gift-envelopes', GiftEnvelopeController::class)
                ->parameters(['gift-envelopes' => 'giftEnvelope']);
            Route::apiResource('weddings.expenses', ExpenseController::class);
            Route::apiResource('weddings.expenses.payments', ExpensePaymentController::class)->except('show');

            Route::controller(ExpensePaymentReceiptController::class)
                ->prefix('weddings/{wedding}/expenses/{expense}/payments/{payment}/receipt')
                ->name('weddings.expenses.payments.receipt.')
                ->group(function () {
                    Route::get('/', 'show')->name('show');
                    Route::post('/', 'store')->name('store');
                    Route::delete('/', 'destroy')->name('destroy');
                });
        });
    });
});
