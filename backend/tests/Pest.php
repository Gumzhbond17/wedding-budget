<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the framework and run against the MySQL test database
| ("wedding-budget-testing", see phpunit.xml). RefreshDatabase migrates once
| per run and wraps each test in a rolled-back transaction.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Raw database fixtures
|--------------------------------------------------------------------------
|
| Query-builder inserts for schema-level tests that must hold even when
| models are bypassed. Model factories cover everything else.
|
*/

/**
 * Insert a full wedding graph, including a soft-deleted expense, and return its ids.
 *
 * @return array{user: int, wedding: int, revenue_category: int, expense_category: int, expense: int, trashed_expense: int}
 */
function seedWeddingGraph(string $currency = 'LAK', ?string $receiptPath = null): array
{
    $now = now();
    $user = DB::table('users')->insertGetId([
        'name' => 'Owner', 'email' => uniqid('owner').'@example.test', 'password' => 'secret', 'created_at' => $now,
    ]);
    $wedding = DB::table('weddings')->insertGetId(['user_id' => $user, 'name' => 'Our wedding', 'created_at' => $now]);
    $revenueCategory = DB::table('categories')->insertGetId([
        'wedding_id' => $wedding, 'type' => 'revenue', 'name' => 'Guest Gifts', 'created_at' => $now,
    ]);
    $expenseCategory = DB::table('categories')->insertGetId([
        'wedding_id' => $wedding, 'type' => 'expense', 'name' => 'Venue', 'planned_amount' => '150000000.00', 'created_at' => $now,
    ]);
    DB::table('exchange_rates')->insert([
        'wedding_id' => $wedding, 'from_currency' => 'USD', 'to_currency' => 'LAK', 'rate' => '21850.00000000', 'effective_date' => '2026-11-01',
    ]);
    DB::table('revenues')->insert([
        'wedding_id' => $wedding, 'category_id' => $revenueCategory, 'source' => 'Parents', 'amount' => '50000000.00',
        'currency' => 'LAK', 'exchange_rate' => '1', 'base_amount' => '50000000.00', 'created_at' => $now,
    ]);
    $expense = DB::table('expenses')->insertGetId(expenseRow($wedding, $expenseCategory, $currency));
    DB::table('expense_payments')->insert([...paymentRow($expense, $currency), 'receipt_path' => $receiptPath]);

    // A soft-deleted expense still holds physical FK references, so deletes must handle it too.
    $trashed = DB::table('expenses')->insertGetId([...expenseRow($wedding, $expenseCategory, $currency), 'deleted_at' => $now]);
    DB::table('expense_payments')->insert(paymentRow($trashed, $currency));

    return [
        'user' => $user,
        'wedding' => $wedding,
        'revenue_category' => $revenueCategory,
        'expense_category' => $expenseCategory,
        'expense' => $expense,
        'trashed_expense' => $trashed,
    ];
}

/** @return array<string, mixed> */
function expenseRow(int $wedding, int $category, string $currency = 'LAK'): array
{
    return [
        'wedding_id' => $wedding, 'category_id' => $category, 'description' => 'Hall rental', 'currency' => $currency,
        'exchange_rate' => '1', 'estimated_amount' => '1000.00', 'base_estimated_amount' => '1000.00', 'created_at' => now(),
    ];
}

/** @return array<string, mixed> */
function paymentRow(int $expense, string $currency = 'LAK'): array
{
    return [
        'expense_id' => $expense, 'amount' => '400.00', 'currency' => $currency, 'exchange_rate' => '1',
        'base_amount' => '400.00', 'paid_date' => '2026-11-10', 'created_at' => now(),
    ];
}

/**
 * Row counts per domain table that belong to the given wedding.
 *
 * @return array{weddings: int, categories: int, exchange_rates: int, revenues: int, expenses: int, expense_payments: int}
 */
function weddingRowCounts(int $wedding): array
{
    $expenseIds = DB::table('expenses')->select('id')->where('wedding_id', $wedding);

    return [
        'weddings' => DB::table('weddings')->where('id', $wedding)->count(),
        'categories' => DB::table('categories')->where('wedding_id', $wedding)->count(),
        'exchange_rates' => DB::table('exchange_rates')->where('wedding_id', $wedding)->count(),
        'revenues' => DB::table('revenues')->where('wedding_id', $wedding)->count(),
        'expenses' => DB::table('expenses')->where('wedding_id', $wedding)->count(),
        'expense_payments' => DB::table('expense_payments')->whereIn('expense_id', $expenseIds)->count(),
    ];
}
