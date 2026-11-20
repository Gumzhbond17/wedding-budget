<?php

use App\Exceptions\UniqueConstraintMessages;
use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    // API-only app: no web routes. Sanctum registers its own /sanctum/csrf-cookie route.
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA auth: requests from SANCTUM_STATEFUL_DOMAINS get cookies, a session and
        // CSRF protection; everything else is treated as stateless.
        $middleware->statefulApi();

        // API only, no login page: a guest is answered 401, never redirected. Without this, a
        // request lacking "Accept: application/json" made the auth middleware look up a "login"
        // route and fail with a 500.
        $middleware->redirectGuestsTo(fn (): ?string => null);

        // Global rather than api-group only, so 404s for unknown routes are translated too.
        $middleware->append(SetLocaleFromAcceptLanguage::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A duplicate that slipped past validation (two concurrent requests) is still a client
        // error: same 422 {message, errors} body as a Form Request failure, never a 500.
        $exceptions->map(
            UniqueConstraintViolationException::class,
            fn (UniqueConstraintViolationException $e) => UniqueConstraintMessages::toValidationException($e),
        );

        // One body for every 404 (missing route, missing model, another user's record), so the
        // response never reveals a model name or whether an id exists.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && $request->is('api/*')) {
                return response()->json(['message' => __('Not found.')], Response::HTTP_NOT_FOUND);
            }

            return $response;
        });
    })->create();
