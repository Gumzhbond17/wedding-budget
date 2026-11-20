<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use App\Services\RecordsCsvExporter;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view,wedding'),
        ];
    }

    public function revenues(Wedding $wedding, RecordsCsvExporter $exporter): StreamedResponse
    {
        return $exporter->revenues($wedding);
    }

    public function expenses(Wedding $wedding, RecordsCsvExporter $exporter): StreamedResponse
    {
        return $exporter->expenses($wedding);
    }

    public function giftEnvelopes(Wedding $wedding, RecordsCsvExporter $exporter): StreamedResponse
    {
        return $exporter->giftEnvelopes($wedding);
    }
}
