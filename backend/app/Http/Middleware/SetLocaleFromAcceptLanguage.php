<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the frontend's language: the SPA sends its current locale as Accept-Language.
 *
 * Only supported locales are ever applied, so the header cannot point the translator at an
 * arbitrary lang/ directory. A Lao string that is not translated yet falls back to English
 * per key (config app.fallback_locale), so partial translations are safe.
 */
class SetLocaleFromAcceptLanguage
{
    /** The first entry is the default for a missing or unsupported header. */
    public const array SUPPORTED = ['en', 'lo'];

    public function handle(Request $request, Closure $next): Response
    {
        // getPreferredLanguage() honours q-values and matches "lo-LA" to "lo".
        $locale = $request->hasHeader('Accept-Language')
            ? $request->getPreferredLanguage(self::SUPPORTED)
            : self::SUPPORTED[0];

        app()->setLocale($locale ?? self::SUPPORTED[0]);

        $response = $next($request);

        // Shared caches must not serve one language's response to the other.
        $response->headers->set('Vary', trim($response->headers->get('Vary').', Accept-Language', ', '));

        return $response;
    }
}
