<?php

use App\Actions\Expenses\ManageExpense;
use App\Actions\Revenues\ManageRevenue;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

/*
| category_id arrives in the request body, so route model binding cannot protect it.
| The write actions must reject any category outside the wedding or of the wrong type.
*/

dataset('foreign or mismatched categories', [
    'from another user\'s wedding' => fn () => fn (string $type) => Category::factory()->{$type}()
        ->for(Wedding::factory()->for(User::factory()))->create()->id,
    'from the user\'s other wedding' => fn () => fn (string $type, Wedding $wedding) => Category::factory()->{$type}()
        ->for(Wedding::factory()->for($wedding->user))->create()->id,
    'of the wrong type' => fn () => fn (string $type, Wedding $wedding) => Category::factory()
        ->{$type === 'revenue' ? 'expense' : 'revenue'}()->for($wedding)->create()->id,
    'that does not exist' => fn () => fn () => 999_999,
]);

it('rejects creating revenue with such a category', function (Closure $categoryId) {
    $wedding = Wedding::factory()->create();

    expect(fn () => app(ManageRevenue::class)->create($wedding, [
        'category_id' => $categoryId('revenue', $wedding), 'source' => 'Parents', 'amount' => '100.00',
    ]))->toThrow(ValidationException::class, 'The selected category is not a revenue category of this wedding.')
        ->and($wedding->revenues()->count())->toBe(0);
})->with('foreign or mismatched categories');

it('rejects moving revenue to such a category', function (Closure $categoryId) {
    $revenue = Revenue::factory()->create();
    $original = $revenue->category_id;

    expect(fn () => app(ManageRevenue::class)->update($revenue, ['category_id' => $categoryId('revenue', $revenue->wedding()->sole())]))
        ->toThrow(ValidationException::class, 'The selected category is not a revenue category of this wedding.')
        ->and($revenue->refresh()->category_id)->toBe($original);
})->with('foreign or mismatched categories');

it('rejects creating an expense with such a category', function (Closure $categoryId) {
    $wedding = Wedding::factory()->create();

    expect(fn () => app(ManageExpense::class)->create($wedding, [
        'category_id' => $categoryId('expense', $wedding), 'description' => 'Hall', 'estimated_amount' => '100.00',
    ]))->toThrow(ValidationException::class, 'The selected category is not a expense category of this wedding.')
        ->and($wedding->expenses()->count())->toBe(0);
})->with('foreign or mismatched categories');

it('rejects moving an expense to such a category', function (Closure $categoryId) {
    $expense = Expense::factory()->create();
    $original = $expense->category_id;

    expect(fn () => app(ManageExpense::class)->update($expense, ['category_id' => $categoryId('expense', $expense->wedding()->sole())]))
        ->toThrow(ValidationException::class, 'The selected category is not a expense category of this wedding.')
        ->and($expense->refresh()->category_id)->toBe($original);
})->with('foreign or mismatched categories');

it('accepts a matching category of the same wedding', function () {
    $revenue = Revenue::factory()->create();
    $other = Category::factory()->revenue()->for($revenue->wedding()->sole())->create();

    app(ManageRevenue::class)->update($revenue, ['category_id' => $other->id]);

    expect($revenue->refresh()->category_id)->toBe($other->id);
});
