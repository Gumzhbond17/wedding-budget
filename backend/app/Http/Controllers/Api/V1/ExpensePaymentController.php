<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\ManageExpensePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreExpensePaymentRequest;
use App\Http\Requests\Payments\UpdateExpensePaymentRequest;
use App\Http\Resources\ExpensePaymentResource;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpensePaymentController extends Controller implements HasMiddleware
{
    /**
     * Listing and creating payments are reads and writes of the parent expense.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:view,expense', only: ['index']),
            new Middleware('can:update,expense', only: ['store']),
            new Middleware('can:update,payment', only: ['update']),
            new Middleware('can:delete,payment', only: ['destroy']),
        ];
    }

    /**
     * Not paginated: payments per expense are few (deposit, instalments, balance).
     */
    public function index(Wedding $wedding, Expense $expense): AnonymousResourceCollection
    {
        return ExpensePaymentResource::collection($expense->payments()->orderBy('paid_date')->orderBy('id')->get());
    }

    public function store(StoreExpensePaymentRequest $request, Wedding $wedding, Expense $expense, ManageExpensePayment $payments): JsonResponse
    {
        $payment = $payments->create($expense, $request->validated());

        return ExpensePaymentResource::make($payment)->response()->setStatusCode(201);
    }

    public function update(UpdateExpensePaymentRequest $request, Wedding $wedding, Expense $expense, ExpensePayment $payment, ManageExpensePayment $payments): ExpensePaymentResource
    {
        return ExpensePaymentResource::make($payments->update($payment, $request->validated()));
    }

    public function destroy(Wedding $wedding, Expense $expense, ExpensePayment $payment, ManageExpensePayment $payments): Response
    {
        $payments->delete($payment);

        return response()->noContent();
    }
}
