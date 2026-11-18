<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    /**
     * Always 200: a JsonResource answers 201 for a model created in the same request
     * (e.g. right after registration), which is wrong for a read.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response()->setStatusCode(200);
    }
}
