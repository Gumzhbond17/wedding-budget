<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA authentication: a cookie session on the "web" guard, protected by CSRF.
 */
class SessionController extends Controller
{
    public function store(LoginRequest $request): UserResource
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            // Same message for unknown email and wrong password: no account enumeration.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // New session id after login prevents session fixation.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return UserResource::make(Auth::guard('web')->user());
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
