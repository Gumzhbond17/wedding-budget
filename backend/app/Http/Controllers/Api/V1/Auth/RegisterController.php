<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Create the account and start a session, so the SPA is logged in right away.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::guard('web')->login($user);

        // New session id after a privilege change prevents session fixation.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
