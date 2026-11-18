<?php

use App\Enums\BudgetHealth;
use BcMath\Number;

it('rates spending against the plan', function (string $spent, ?string $planned, ?BudgetHealth $health) {
    expect(BudgetHealth::of(new Number($spent), $planned === null ? null : new Number($planned)))->toBe($health);
})->with([
    'nothing spent' => ['0.00', '1000.00', BudgetHealth::Green],
    'just under 90%' => ['899.99', '1000.00', BudgetHealth::Green],
    'exactly 90%' => ['900.00', '1000.00', BudgetHealth::Amber],
    'exactly 100%' => ['1000.00', '1000.00', BudgetHealth::Amber],
    'one cent over' => ['1000.01', '1000.00', BudgetHealth::Red],
    'large LAK values' => ['450000000.01', '450000000.00', BudgetHealth::Red],
    'zero plan, nothing spent' => ['0.00', '0.00', BudgetHealth::Green],
    'zero plan, something spent' => ['0.01', '0.00', BudgetHealth::Red],
    'no plan' => ['5000.00', null, null],
]);
