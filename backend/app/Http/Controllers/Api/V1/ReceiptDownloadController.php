<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\FindReceiptPayment;
use App\Http\Controllers\Controller;
use App\Models\ExpensePayment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a receipt for a URL signed by ExpensePaymentReceiptController::show().
 *
 * The "signed" middleware is the only gate here: the signature covers the payment id and the
 * expiry, and it is issued only after the payment policy passed. No session is needed, so the
 * link also opens in a new tab or an <img>/<iframe> on the frontend origin.
 */
class ReceiptDownloadController extends Controller
{
    /** Extension used when the client's file name is unusable. */
    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Takes the raw id instead of route-model binding on purpose: binding runs before the
     * "signed" middleware, so a tampered URL would answer 404 for unknown ids and 403 for
     * existing ones, revealing which payments exist. The lookup now happens only after the
     * signature has been verified.
     */
    public function __invoke(string $payment, FindReceiptPayment $findPayment): StreamedResponse
    {
        $payment = $findPayment->handle($payment);
        $disk = Storage::disk('receipts');

        abort_if($payment?->receipt_path === null || ! $disk->exists($payment->receipt_path), 404);

        return $disk->response($payment->receipt_path, headers: [
            // MIME type detected from the content at upload, never the client's claim.
            'Content-Type' => $payment->receipt_mime_type,
            'Content-Disposition' => $this->disposition($payment),
            'X-Content-Type-Options' => 'nosniff',
            // The file never runs scripts or loads anything, even if a viewer would allow it.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            // Not stored by browsers or shared caches; the signed URL is not passed on as a Referer.
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * Images are shown inline under the strict CSP above. PDFs are downloaded instead: browser
     * PDF viewers are full document renderers (forms, scripts, links) and some refuse to run
     * under a sandbox CSP, so the file is handed to the user's own viewer rather than rendered
     * on the API origin.
     *
     * The client's original name is user input: control characters, quotes, slashes or "%"
     * could break the header or Symfony's validation (a 500), so only letters (with combining
     * marks, e.g. Lao vowels), digits, spaces and ". _ - ( )" are kept, with an ASCII fallback
     * for old clients.
     */
    private function disposition(ExpensePayment $payment): string
    {
        $extension = self::EXTENSIONS[$payment->receipt_mime_type] ?? 'bin';

        $name = Str::of(pathinfo((string) $payment->receipt_original_name, PATHINFO_FILENAME))
            ->replaceMatches('/[^\pL\pM\pN ._()-]+/u', '')
            ->squish()
            ->trim('. ')
            ->limit(100, '')
            ->value();

        $name = ($name === '' ? 'receipt' : $name).'.'.$extension;
        $ascii = Str::of(Str::ascii($name))->replaceMatches('/[^A-Za-z0-9 ._()-]+/', '')->value();

        return HeaderUtils::makeDisposition(
            $payment->receipt_mime_type === 'application/pdf'
                ? HeaderUtils::DISPOSITION_ATTACHMENT
                : HeaderUtils::DISPOSITION_INLINE,
            $name,
            $ascii === '.'.$extension ? "receipt.{$extension}" : $ascii,
        );
    }
}
