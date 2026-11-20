<?php

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\GiftEnvelope;
use App\Models\Revenue;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\MassAssignmentException;

/*
| Server-computed and ownership columns must never be mass assignable, so a client-sent
| exchange_rate, base amount, paid total or owner id cannot reach the database.
| Strict mode (non-production) turns a discarded attribute into an exception.
*/

it('refuses to mass assign server-owned columns', function (string $model, string $column) {
    expect(fn () => new $model([$column => '1']))->toThrow(MassAssignmentException::class);
})->with([
    'wedding owner' => [Wedding::class, 'user_id'],
    'revenue rate' => [Revenue::class, 'exchange_rate'],
    'revenue base amount' => [Revenue::class, 'base_amount'],
    'revenue wedding' => [Revenue::class, 'wedding_id'],
    'gift envelope wedding' => [GiftEnvelope::class, 'wedding_id'],
    'expense rate' => [Expense::class, 'exchange_rate'],
    'expense base estimate' => [Expense::class, 'base_estimated_amount'],
    'expense base actual' => [Expense::class, 'base_actual_amount'],
    'expense paid total' => [Expense::class, 'paid_amount'],
    'expense base paid total' => [Expense::class, 'base_paid_amount'],
    'payment currency' => [ExpensePayment::class, 'currency'],
    'payment rate' => [ExpensePayment::class, 'exchange_rate'],
    'payment base amount' => [ExpensePayment::class, 'base_amount'],
    'payment receipt path' => [ExpensePayment::class, 'receipt_path'],
]);
