<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Receipts are images or PDFs up to 5 MB. "mimetypes" inspects the file content, not the
 * client-supplied extension or Content-Type, so a renamed HTML or SVG file is rejected.
 */
class StoreReceiptRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 5 * 1024;

    /** @var list<string> */
    public const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimetypes:'.implode(',', self::MIME_TYPES), 'max:'.self::MAX_KILOBYTES],
        ];
    }
}
