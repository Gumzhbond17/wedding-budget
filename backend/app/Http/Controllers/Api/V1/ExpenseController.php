<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\ListExpensePayments;
use App\Actions\Expenses\ListExpenses;
use App\Actions\Expenses\ManageExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ListExpensesRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpenseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Expense,wedding', only: ['index']),
            new Middleware('can:create,App\Models\Expense,wedding', only: ['store']),
            new Middleware('can:view,expense', only: ['show']),
            new Middleware('can:update,expense', only: ['update']),
            new Middleware('can:delete,expense', only: ['destroy']),
        ];
    }

    public function index(ListExpensesRequest $request, Wedding $wedding, ListExpenses $expenses): AnonymousResourceCollection
    {
        return ExpenseResource::collection($expenses->handle($wedding, $request->criteria('-created_at')));
    }

    public function store(StoreExpenseRequest $request, Wedding $wedding, ManageExpense $expenses): JsonResponse
    {
        $expense = $expenses->create($wedding, $request->validated());

        return ExpenseResource::make($expense->refresh()->setRelation('wedding', $wedding)->load('category'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Wedding $wedding, Expense $expense, ListExpensePayments $payments): ExpenseResource
    {
        return ExpenseResource::make($expense->load('category')->setRelation('payments', $payments->handle($expense)));
    }

    public function update(UpdateExpenseRequest $request, Wedding $wedding, Expense $expense, ManageExpense $expenses): ExpenseResource
    {
        return ExpenseResource::make($expenses->update($expense, $request->validated())->load('category'));
    }

    /**
     * Soft delete: excluded from totals, kept for history together with its payments.
     */
    public function destroy(Wedding $wedding, Expense $expense, ManageExpense $expenses): Response
    {
        $expenses->delete($expense);

        return response()->noContent();
    }
}
