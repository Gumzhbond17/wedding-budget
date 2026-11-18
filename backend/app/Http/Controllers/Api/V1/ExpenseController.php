<?php

namespace App\Http\Controllers\Api\V1;

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

    /**
     * Date range filters on due_date.
     */
    public function index(ListExpensesRequest $request, Wedding $wedding): AnonymousResourceCollection
    {
        [$column, $direction] = $request->sortColumnAndDirection('-created_at');

        $expenses = $wedding->expenses()
            ->with('category')
            ->when($request->validated('category_id'), fn ($query, $id) => $query->where('category_id', $id))
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('currency'), fn ($query, $currency) => $query->where('currency', $currency))
            ->when($request->validated('date_from'), fn ($query, $from) => $query->where('due_date', '>=', $from))
            ->when($request->validated('date_to'), fn ($query, $to) => $query->where('due_date', '<=', $to))
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate($request->perPage())
            ->withQueryString();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request, Wedding $wedding, ManageExpense $expenses): JsonResponse
    {
        $expense = $expenses->create($wedding, $request->validated());

        return ExpenseResource::make($expense->refresh()->setRelation('wedding', $wedding)->load('category'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Wedding $wedding, Expense $expense): ExpenseResource
    {
        return ExpenseResource::make($expense->load(['category', 'payments' => fn ($query) => $query->orderBy('paid_date')]));
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
