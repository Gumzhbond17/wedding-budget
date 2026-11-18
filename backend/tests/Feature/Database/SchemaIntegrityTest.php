<?php

/*
| Schema-level guarantees, tested with raw query builder writes on purpose:
| these rules must hold even when the application layer (models, Form Requests)
| is bypassed. Runs on MySQL, because CHECK constraints and InnoDB foreign key
| behaviour are exactly what SQLite would not reproduce.
*/

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('foreign key delete rules', function () {
    it('refuses a plain SQL delete of a wedding that has records', function () {
        $ids = seedWeddingGraph();
        $before = weddingRowCounts($ids['wedding']);

        expect(fn () => DB::table('weddings')->where('id', $ids['wedding'])->delete())
            ->toThrow(QueryException::class, 'Cannot delete or update a parent row')
            ->and(weddingRowCounts($ids['wedding']))->toBe($before);
    });

    it('refuses to delete a user who still owns a wedding', function () {
        $ids = seedWeddingGraph();

        expect(fn () => DB::table('users')->where('id', $ids['user'])->delete())
            ->toThrow(QueryException::class, 'weddings_user_id_foreign');
    });

    it('deletes payments when their expense is force deleted', function () {
        $ids = seedWeddingGraph();

        DB::table('expenses')->where('id', $ids['expense'])->delete();

        expect(DB::table('expense_payments')->where('expense_id', $ids['expense'])->count())->toBe(0);
    });
});

describe('referential integrity', function () {
    it('rejects a record that uses a category from another wedding', function () {
        $mine = seedWeddingGraph();
        $theirs = seedWeddingGraph();

        expect(fn () => DB::table('expenses')->insert(expenseRow($mine['wedding'], $theirs['expense_category'])))
            ->toThrow(QueryException::class, 'expenses_wedding_id_category_id_foreign');
    });

    it('rejects a payment whose currency differs from its expense', function () {
        $ids = seedWeddingGraph('USD');

        expect(fn () => DB::table('expense_payments')->insert(paymentRow($ids['expense'], 'LAK')))
            ->toThrow(QueryException::class, 'expense_payments_expense_id_currency_foreign');
    });

    it('blocks changing an expense currency once payments exist', function () {
        $ids = seedWeddingGraph('USD');

        expect(fn () => DB::table('expenses')->where('id', $ids['expense'])->update(['currency' => 'THB']))
            ->toThrow(QueryException::class, 'expense_payments_expense_id_currency_foreign');
    });

    it('keeps category names unique per wedding but not across weddings', function () {
        $first = seedWeddingGraph();
        $second = seedWeddingGraph();

        expect(fn () => DB::table('categories')->insert(['wedding_id' => $first['wedding'], 'type' => 'expense', 'name' => 'Venue']))
            ->toThrow(QueryException::class, 'categories_wedding_id_type_name_unique')
            ->and(DB::table('categories')->where('name', 'Venue')->whereIn('wedding_id', [$first['wedding'], $second['wedding']])->count())
            ->toBe(2);
    });
});

describe('check constraints', function () {
    it('rejects invalid values', function (string $table, Closure $row, string $constraint) {
        $ids = seedWeddingGraph();

        expect(fn () => DB::table($table)->insert($row($ids)))
            ->toThrow(QueryException::class, "Check constraint '{$constraint}' is violated");
    })->with([
        'negative budget target' => ['weddings', fn ($ids) => ['user_id' => $ids['user'], 'name' => 'W', 'budget_target' => '-1'], 'weddings_budget_target_check'],
        'negative planned amount' => ['categories', fn ($ids) => ['wedding_id' => $ids['wedding'], 'type' => 'expense', 'name' => 'X', 'planned_amount' => '-1'], 'categories_planned_amount_check'],
        'planned amount on revenue category' => ['categories', fn ($ids) => ['wedding_id' => $ids['wedding'], 'type' => 'revenue', 'name' => 'X', 'planned_amount' => '10'], 'categories_planned_amount_type_check'],
        'zero rate' => ['exchange_rates', fn ($ids) => ['wedding_id' => $ids['wedding'], 'from_currency' => 'THB', 'to_currency' => 'LAK', 'rate' => '0', 'effective_date' => '2026-11-01'], 'exchange_rates_rate_check'],
        'same currency pair' => ['exchange_rates', fn ($ids) => ['wedding_id' => $ids['wedding'], 'from_currency' => 'LAK', 'to_currency' => 'LAK', 'rate' => '1', 'effective_date' => '2026-11-01'], 'exchange_rates_pair_check'],
        'negative revenue amount' => ['revenues', fn ($ids) => ['wedding_id' => $ids['wedding'], 'category_id' => $ids['revenue_category'], 'source' => 'S', 'amount' => '-5', 'currency' => 'LAK', 'exchange_rate' => '1', 'base_amount' => '0'], 'revenues_amount_check'],
        'zero revenue rate' => ['revenues', fn ($ids) => ['wedding_id' => $ids['wedding'], 'category_id' => $ids['revenue_category'], 'source' => 'S', 'amount' => '5', 'currency' => 'LAK', 'exchange_rate' => '0', 'base_amount' => '0'], 'revenues_exchange_rate_check'],
        'negative actual amount' => ['expenses', fn ($ids) => [...expenseRow($ids['wedding'], $ids['expense_category']), 'actual_amount' => '-1'], 'expenses_actual_amount_check'],
        'negative paid amount' => ['expenses', fn ($ids) => [...expenseRow($ids['wedding'], $ids['expense_category']), 'paid_amount' => '-1'], 'expenses_paid_amount_check'],
        'zero payment' => ['expense_payments', fn ($ids) => [...paymentRow($ids['expense']), 'amount' => '0'], 'expense_payments_amount_check'],
        'zero payment rate' => ['expense_payments', fn ($ids) => [...paymentRow($ids['expense']), 'exchange_rate' => '0'], 'expense_payments_exchange_rate_check'],
    ]);

    it('accepts a zero estimate', function () {
        $ids = seedWeddingGraph();

        DB::table('expenses')->insert([...expenseRow($ids['wedding'], $ids['expense_category']), 'estimated_amount' => '0', 'base_estimated_amount' => '0']);

        expect(DB::table('expenses')->where('wedding_id', $ids['wedding'])->where('estimated_amount', 0)->exists())->toBeTrue();
    });

    it('stores large LAK amounts exactly', function () {
        $ids = seedWeddingGraph();

        DB::table('weddings')->where('id', $ids['wedding'])->update(['budget_target' => '999999999999999999.99']);

        expect(DB::table('weddings')->where('id', $ids['wedding'])->value('budget_target'))->toBe('999999999999999999.99');
    });
});
