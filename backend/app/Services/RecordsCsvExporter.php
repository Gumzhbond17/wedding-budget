<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Revenue;
use App\Models\Wedding;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports of a wedding's revenues and expenses (soft-deleted records excluded).
 *
 * - Streamed in chunks of CHUNK rows, so memory stays flat however many records there are.
 * - UTF-8 with a BOM: without it Excel guesses a legacy code page and Lao text is garbled.
 * - Every row shows the original amount, its currency, the snapshotted rate and the base amount.
 * - Cells are escaped against formula injection (see escape()).
 */
final class RecordsCsvExporter
{
    private const int CHUNK = 500;

    private const string BOM = "\u{FEFF}";

    public function revenues(Wedding $wedding): StreamedResponse
    {
        $base = $wedding->base_currency->value;

        return $this->stream($wedding, 'revenues', $wedding->revenues()->with('category'), [
            'ID', 'Category', 'Source', 'Status', 'Expected date', 'Received date', 'Payment method',
            'Amount', 'Currency', 'Exchange rate', "Base amount ({$base})", 'Notes',
        ], fn (Revenue $revenue): array => [
            $revenue->id,
            $revenue->category->name,
            $revenue->source,
            $revenue->status->value,
            $revenue->expected_date?->toDateString(),
            $revenue->received_date?->toDateString(),
            $revenue->payment_method?->value,
            $revenue->amount,
            $revenue->currency->value,
            $revenue->exchange_rate,
            $revenue->base_amount,
            $revenue->notes,
        ]);
    }

    public function expenses(Wedding $wedding): StreamedResponse
    {
        $base = $wedding->base_currency->value;

        return $this->stream($wedding, 'expenses', $wedding->expenses()->with('category'), [
            'ID', 'Category', 'Description', 'Vendor', 'Status', 'Due date',
            'Currency', 'Exchange rate', 'Estimated amount', 'Actual amount', 'Paid amount', 'Outstanding amount', 'Outstanding basis',
            "Base estimated ({$base})", "Base actual ({$base})", "Base paid ({$base})", 'Notes',
        ], fn (Expense $expense): array => [
            $expense->id,
            $expense->category->name,
            $expense->description,
            $expense->vendor_name,
            $expense->status->value,
            $expense->due_date?->toDateString(),
            $expense->currency->value,
            $expense->exchange_rate,
            $expense->estimated_amount,
            $expense->actual_amount,
            $expense->paid_amount,
            $expense->outstanding_amount,
            $expense->actual_amount === null ? 'estimated' : 'actual',
            $expense->base_estimated_amount,
            $expense->base_actual_amount,
            $expense->base_paid_amount,
            $expense->notes,
        ]);
    }

    /**
     * Neutralize spreadsheet formulas (OWASP "CSV injection"): a cell starting with = + - @
     * (or a tab / carriage return, which some spreadsheets strip before parsing) is prefixed with
     * a single quote, so text like "=HYPERLINK(...)" in a vendor name is shown, never run.
     * Amounts cannot be negative (DB CHECK), so no number is altered.
     */
    public static function escape(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /**
     * @param  HasMany<covariant Revenue|Expense, Wedding>  $records
     * @param  list<string>  $header
     * @param  Closure(Revenue|Expense): list<mixed>  $row
     */
    private function stream(Wedding $wedding, string $type, HasMany $records, array $header, Closure $row): StreamedResponse
    {
        $suffix = sprintf('-%s-%s.csv', $type, now(DashboardSummaryService::TIMEZONE)->format('Y-m-d'));

        return response()->stream(function () use ($records, $header, $row): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, self::BOM);
            $this->write($output, $header);

            // lazyById pages on the primary key, so rows added during the export cannot shift pages.
            foreach ($records->lazyById(self::CHUNK) as $record) {
                $this->write($output, $row($record));
            }

            fclose($output);
        }, 200, [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                self::fileNamePart($wedding->name, $wedding->getKey()).$suffix,
                self::asciiFileNamePart($wedding->name, $wedding->getKey()).$suffix,
            ),
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The wedding name as a file name part, Lao text kept ("ງານແຕ່ງ Vanh & Noy" → "ງານແຕ່ງ-Vanh-&-Noy").
     * Characters Windows refuses in file names (/ \ : * ? " < > |), control and other invisible
     * characters, and "%" (which Symfony refuses in a disposition) are replaced with a hyphen;
     * runs of hyphens, dots and spaces become one hyphen ("A/B: 100%" → "A-B-100").
     */
    public static function fileNamePart(string $weddingName, int $weddingId): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|%\p{C}]/u', '-', $weddingName) ?? '';
        $name = trim(preg_replace('/[\s.-]+/u', '-', $name) ?? '', '-');
        $name = mb_substr($name, 0, 80);

        return $name === '' ? self::asciiFileNamePart($weddingName, $weddingId) : $name;
    }

    /**
     * ASCII fallback for clients that ignore filename* (RFC 6266): "Vanh & Noy" → "vanh-noy",
     * or "wedding-12" when nothing ASCII is left, e.g. for an all-Lao name.
     */
    public static function asciiFileNamePart(string $weddingName, int $weddingId): string
    {
        $slug = Str::slug(Str::ascii($weddingName));

        return $slug === '' ? 'wedding-'.$weddingId : mb_substr($slug, 0, 80);
    }

    /**
     * @param  resource  $output
     * @param  list<mixed>  $cells
     */
    private function write($output, array $cells): void
    {
        // Explicit escape "" (RFC 4180 quoting only; PHP 8.4 deprecates the default "\\") and CRLF for Excel.
        fputcsv($output, array_map(self::escape(...), $cells), ',', '"', '', "\r\n");
    }
}
