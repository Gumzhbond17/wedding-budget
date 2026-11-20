<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\GiftEnvelope;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;
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
    DB::table('gift_envelopes')->insert([envelopeRow($wedding), [...envelopeRow($wedding), 'deleted_at' => $now]]);
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
function envelopeRow(int $wedding): array
{
    return [
        'wedding_id' => $wedding, 'full_name' => 'Bounmy Sengdara', 'amount' => '500000.00', 'guest_group' => 'friends',
        'received_date' => '2026-11-10', 'deleted_at' => null, 'created_at' => now(),
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
 * @return array{weddings: int, categories: int, exchange_rates: int, revenues: int, gift_envelopes: int, expenses: int, expense_payments: int}
 */
function weddingRowCounts(int $wedding): array
{
    $expenseIds = DB::table('expenses')->select('id')->where('wedding_id', $wedding);

    return [
        'weddings' => DB::table('weddings')->where('id', $wedding)->count(),
        'categories' => DB::table('categories')->where('wedding_id', $wedding)->count(),
        'exchange_rates' => DB::table('exchange_rates')->where('wedding_id', $wedding)->count(),
        'revenues' => DB::table('revenues')->where('wedding_id', $wedding)->count(),
        'gift_envelopes' => DB::table('gift_envelopes')->where('wedding_id', $wedding)->count(),
        'expenses' => DB::table('expenses')->where('wedding_id', $wedding)->count(),
        'expense_payments' => DB::table('expense_payments')->whereIn('expense_id', $expenseIds)->count(),
    ];
}

/*
|--------------------------------------------------------------------------
| HTTP fixtures
|--------------------------------------------------------------------------
*/

/**
 * A wedding owned by $user with one of every nested resource, including a payment with a
 * receipt path. Records are created through factories; the receipts disk should be faked.
 *
 * @return array{wedding: Wedding, category: Category, exchangeRate: ExchangeRate, revenue: Revenue, giftEnvelope: GiftEnvelope, expense: Expense, payment: ExpensePayment}
 */
function weddingGraphFor(User $user): array
{
    $wedding = Wedding::factory()->for($user)->create();
    $expense = Expense::factory()->for($wedding)->invoiced()->create();
    $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100000.00']);
    $payment->forceFill(['receipt_path' => "payments/{$payment->id}/receipt.png", 'receipt_original_name' => 'receipt.png', 'receipt_mime_type' => 'image/png', 'receipt_size' => 10])->save();

    return [
        'wedding' => $wedding,
        'category' => Category::factory()->for($wedding)->create(),
        'exchangeRate' => ExchangeRate::factory()->for($wedding)->create(),
        'revenue' => Revenue::factory()->for($wedding)->create(),
        'giftEnvelope' => GiftEnvelope::factory()->for($wedding)->create(),
        'expense' => $expense,
        'payment' => $payment,
    ];
}

/*
| One LAK-based wedding with every case the dashboard must handle. Rates: USD 21,850 (expenses),
| 21,800 (a revenue), 21,900 and 22,000 (payment days); THB 620 and 615.
|
| Expenses (base LAK):
|   E1 Venue LAK        est 90,000,000  actual 95,000,000  paid 95,000,000 (2026-10-05)        paid
|   E2 Catering USD     est 3,000 = 65,550,000  actual 3,200 = 69,920,000
|                       paid 1,000 @ 21,900 = 21,900,000 (2026-11-02)    due today       partially paid
|   E3 Photography THB  est 30,000 = 18,600,000  no actual  unpaid       due in 30 days  booked
|   E4 Photography LAK  est 5,000,000  actual 4,000,000  paid 1,000,000 (2026-07-20)     CANCELLED
|   E5 Attire USD       est = actual 500 = 10,925,000
|                       paid 550 @ 22,000 = 12,100,000 (2026-11-10)  due in 3 days       OVERPAID
|   E6 Venue LAK        est 50,000,000, paid 5,000,000                                   TRASHED
|   E7 Catering LAK     est 2,000,000  due in 31 days                                    planned
|
| Revenues: received 100,000,000 LAK (2026-09-15) and 2,000 USD @ 21,800 = 43,600,000
| (2026-10-20); expected 50,000 THB @ 615 = 30,750,000 and 20,000,000 LAK; a trashed
| received 7,000,000 LAK.
*/

/** @return array{wedding: Wedding, expenses: array<string, Expense>} */
function dashboardFixture(): array
{
    $wedding = Wedding::factory()->create(['budget_target' => '250000000.00']);
    $category = fn (string $name, ?string $planned) => Category::factory()->expense()->for($wedding)
        ->create(['name' => $name, 'planned_amount' => $planned])->id;
    $venue = $category('Venue', '100000000.00');
    $catering = $category('Catering', '80000000.00');
    $photography = $category('Photography & Video', '15000000.00');
    $attire = $category('Attire', null);
    $category('Music & Entertainment', '5000000.00');

    $gifts = Category::factory()->revenue()->for($wedding)->create(['name' => 'Family Contribution'])->id;
    $revenue = fn () => Revenue::factory()->for($wedding)->state(['category_id' => $gifts]);
    $revenue()->received()->create(['amount' => '100000000.00', 'received_date' => '2026-09-15']);
    $revenue()->inCurrency(Currency::USD, '21800.00000000', '2000.00')->received()->create(['received_date' => '2026-10-20']);
    $revenue()->inCurrency(Currency::THB, '615.00000000', '50000.00')->create();
    $revenue()->create(['amount' => '20000000.00']);
    $revenue()->received()->create(['amount' => '7000000.00', 'received_date' => '2026-11-01'])->delete();

    $expense = fn (int $categoryId) => Expense::factory()->for($wedding)->state(['category_id' => $categoryId]);
    $pay = fn (Expense $expense, string $amount, string $rate, string $date) => ExpensePayment::factory()->for($expense)
        ->create(['amount' => $amount, 'exchange_rate' => $rate, 'paid_date' => $date]);

    $e1 = $expense($venue)->invoiced('95000000.00')->create(['estimated_amount' => '90000000.00', 'due_date' => '2026-10-01']);
    $pay($e1, '95000000.00', '1', '2026-10-05');

    $e2 = $expense($catering)->inCurrency(Currency::USD, '21850.00000000', '3000.00')->invoiced('3200.00')->create(['due_date' => '2026-11-18']);
    $pay($e2, '1000.00', '21900.00000000', '2026-11-02');

    $e3 = $expense($photography)->inCurrency(Currency::THB, '620.00000000', '30000.00')->booked()->create(['due_date' => '2026-12-18']);

    $e4 = $expense($photography)->invoiced('4000000.00')->cancelled()->create(['estimated_amount' => '5000000.00', 'due_date' => '2026-11-23']);
    $pay($e4, '1000000.00', '1', '2026-07-20');

    $e5 = $expense($attire)->inCurrency(Currency::USD, '21850.00000000', '500.00')->invoiced()->create(['due_date' => '2026-11-21']);
    $pay($e5, '550.00', '22000.00000000', '2026-11-10');

    $e6 = $expense($venue)->create(['estimated_amount' => '50000000.00', 'due_date' => '2026-11-25']);
    $pay($e6, '5000000.00', '1', '2026-11-01');
    $e6->delete();

    $e7 = $expense($catering)->create(['estimated_amount' => '2000000.00', 'due_date' => '2026-12-19']);

    return ['wedding' => $wedding, 'expenses' => compact('e1', 'e2', 'e3', 'e4', 'e5', 'e6', 'e7')];
}
