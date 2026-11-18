<?php

use App\Models\Expense;
use App\Models\Revenue;
use App\Models\Wedding;

it('reports financial records, including soft-deleted ones', function (string $model) {
    $wedding = Wedding::factory()->create();

    expect($wedding->hasFinancialRecords())->toBeFalse();

    $model::factory()->for($wedding)->create()->delete();

    expect($wedding->hasFinancialRecords())->toBeTrue();
})->with([
    'revenue' => Revenue::class,
    'expense' => Expense::class,
]);
