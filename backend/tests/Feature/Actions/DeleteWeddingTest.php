<?php

use App\Actions\Weddings\DeleteWedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('deletes a wedding with all of its data, including soft-deleted records', function () {
    Storage::fake('receipts');
    Storage::disk('receipts')->put('payments/venue.pdf', 'receipt');
    $ids = seedWeddingGraph(receiptPath: 'payments/venue.pdf');
    $other = seedWeddingGraph();

    expect(weddingRowCounts($ids['wedding']))->toBe([
        'weddings' => 1, 'categories' => 2, 'exchange_rates' => 1, 'revenues' => 1, 'expenses' => 2, 'expense_payments' => 2,
    ]);

    app(DeleteWedding::class)->handle($ids['wedding']);

    expect(weddingRowCounts($ids['wedding']))->each->toBe(0)
        ->and(DB::table('expense_payments')->whereIn('expense_id', [$ids['expense'], $ids['trashed_expense']])->count())->toBe(0)
        ->and(DB::table('users')->where('id', $ids['user'])->exists())->toBeTrue()
        ->and(weddingRowCounts($other['wedding'])['expense_payments'])->toBe(2);

    Storage::disk('receipts')->assertMissing('payments/venue.pdf');
});

it('does nothing for a wedding that does not exist', function () {
    $ids = seedWeddingGraph();

    app(DeleteWedding::class)->handle($ids['wedding'] + 1000);

    expect(weddingRowCounts($ids['wedding'])['weddings'])->toBe(1);
});
