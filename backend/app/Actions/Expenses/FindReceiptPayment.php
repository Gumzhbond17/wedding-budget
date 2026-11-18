<?php

namespace App\Actions\Expenses;

use App\Models\ExpensePayment;

/**
 * Looks up the payment behind a signed receipt URL. Callers run this only after the signature
 * was verified (see ReceiptDownloadController), so unknown and foreign ids are indistinguishable.
 */
final class FindReceiptPayment
{
    public function handle(string $paymentId): ?ExpensePayment
    {
        return ExpensePayment::query()->find($paymentId);
    }
}
