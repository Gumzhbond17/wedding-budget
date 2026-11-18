<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Wedding;
use App\Services\DashboardSummaryService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view,wedding'),
        ];
    }

    public function __invoke(Wedding $wedding, DashboardSummaryService $dashboard): DashboardResource
    {
        return DashboardResource::make($dashboard->summarize($wedding));
    }
}
