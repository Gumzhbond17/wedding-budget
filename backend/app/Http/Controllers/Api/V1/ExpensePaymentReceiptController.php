<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\ManageExpensePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreReceiptRequest;
use App\Http\Resources\ExpensePaymentResource;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\URL;

/**
 * Receipt of one payment. Files live on the private "receipts" disk and are never served by a
 * storage path: show() issues a short-lived signed URL, after the policy check, that
 * ReceiptDownloadController honours.
 */
class ExpensePaymentReceiptController extends Controller implements HasMiddleware
{
    /** Short on purpose: long enough to open the file, too short for a leaked link to matter. */
    public const int SIGNED_URL_MINUTES = 2;

    public static function middleware(): array
    {
        return [
            new Middleware('can:view,payment', only: ['show']),
            new Middleware('can:update,payment', only: ['store', 'destroy']),
        ];
    }

    public function show(Wedding $wedding, Expense $expense, ExpensePayment $payment): JsonResponse
    {
        abort_if($payment->receipt_path === null, 404);

        $expiresAt = now()->addMinutes(self::SIGNED_URL_MINUTES);

        return response()->json([
            'data' => [
                'url' => URL::temporarySignedRoute('v1.receipts.download', $expiresAt, ['payment' => $payment->getKey()]),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function store(StoreReceiptRequest $request, Wedding $wedding, Expense $expense, ExpensePayment $payment, ManageExpensePayment $payments): ExpensePaymentResource
    {
        return ExpensePaymentResource::make($payments->attachReceipt($payment, $request->file('receipt')));
    }

    public function destroy(Wedding $wedding, Expense $expense, ExpensePayment $payment, ManageExpensePayment $payments): ExpensePaymentResource
    {
        return ExpensePaymentResource::make($payments->removeReceipt($payment));
    }
}
