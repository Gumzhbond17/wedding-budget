<?php

namespace App\Actions\Weddings;

use App\Models\Wedding;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes a wedding and everything it owns.
 *
 * Category foreign keys are RESTRICT, so the database will not cascade through financial
 * records on its own. This action deletes children before parents inside one transaction:
 * payments -> expenses / revenues / gift envelopes -> categories -> exchange_rates -> wedding.
 *
 * The query builder is used on purpose: it bypasses the SoftDeletes scope, so trashed
 * revenues and expenses are purged too instead of blocking the category delete.
 */
final class DeleteWedding
{
    public function handle(Wedding $wedding): void
    {
        $weddingId = $wedding->getKey();

        $receiptPaths = DB::transaction(function () use ($weddingId): array {
            // Lock the wedding row so no record can be added to it while it is being deleted.
            // A concurrent request may already have deleted it: then there is nothing to do.
            $exists = DB::table('weddings')->where('id', $weddingId)->lockForUpdate()->exists();

            if (! $exists) {
                return [];
            }

            $payments = DB::table('expense_payments')->whereIn('expense_id', $this->expenseIds($weddingId));

            $receiptPaths = (clone $payments)->whereNotNull('receipt_path')->pluck('receipt_path')->all();

            $payments->delete();
            DB::table('expenses')->where('wedding_id', $weddingId)->delete();
            DB::table('revenues')->where('wedding_id', $weddingId)->delete();
            DB::table('gift_envelopes')->where('wedding_id', $weddingId)->delete();
            DB::table('categories')->where('wedding_id', $weddingId)->delete();
            DB::table('exchange_rates')->where('wedding_id', $weddingId)->delete();
            DB::table('weddings')->where('id', $weddingId)->delete();

            return $receiptPaths;
        });

        // Files go only after the commit: a rolled-back transaction cannot bring a deleted file back.
        if ($receiptPaths !== []) {
            Storage::disk('receipts')->delete($receiptPaths);
        }
    }

    /**
     * Subquery instead of plucked ids, so a wedding with thousands of expenses stays one statement.
     */
    private function expenseIds(int $weddingId): Builder
    {
        return DB::table('expenses')->select('id')->where('wedding_id', $weddingId);
    }
}
