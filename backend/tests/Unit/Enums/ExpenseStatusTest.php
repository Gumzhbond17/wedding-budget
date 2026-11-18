<?php

use App\Enums\ExpenseStatus;
use BcMath\Number;

it('derives the status from payments', function (ExpenseStatus $current, string $paid, ?string $actual, ExpenseStatus $expected) {
    $status = $current->afterPayments(new Number($paid), $actual === null ? null : new Number($actual));

    expect($status)->toBe($expected);
})->with([
    'no payments keeps planned' => [ExpenseStatus::Planned, '0', '100.00', ExpenseStatus::Planned],
    'no payments keeps booked' => [ExpenseStatus::Booked, '0', null, ExpenseStatus::Booked],
    'deposit is partial' => [ExpenseStatus::Booked, '30.00', '100.00', ExpenseStatus::PartiallyPaid],
    'one cent short is partial' => [ExpenseStatus::PartiallyPaid, '99.99', '100.00', ExpenseStatus::PartiallyPaid],
    'exact amount is paid' => [ExpenseStatus::PartiallyPaid, '100.00', '100.00', ExpenseStatus::Paid],
    'overpayment is paid' => [ExpenseStatus::Booked, '120.00', '100.00', ExpenseStatus::Paid],
    'payment without invoice is partial' => [ExpenseStatus::Planned, '50.00', null, ExpenseStatus::PartiallyPaid],
    'last payment removed falls back to booked' => [ExpenseStatus::Paid, '0', '100.00', ExpenseStatus::Booked],
    'cancelled stays cancelled with payments' => [ExpenseStatus::Cancelled, '30.00', '100.00', ExpenseStatus::Cancelled],
    'large LAK amounts compare exactly' => [ExpenseStatus::Booked, '999999999999999999.98', '999999999999999999.99', ExpenseStatus::PartiallyPaid],
]);

it('lets users set only planned, booked and cancelled', function () {
    expect(ExpenseStatus::userSettable())->toBe([ExpenseStatus::Planned, ExpenseStatus::Booked, ExpenseStatus::Cancelled])
        ->and(ExpenseStatus::Paid->isUserSettable())->toBeFalse()
        ->and(ExpenseStatus::PartiallyPaid->isUserSettable())->toBeFalse();
});
