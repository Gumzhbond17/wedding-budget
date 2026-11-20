<?php

use App\Actions\Weddings\DeleteWedding;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('deletes a wedding with all of its data, including soft-deleted records', function () {
    Storage::fake('receipts');
    Storage::disk('receipts')->put('payments/venue.pdf', 'receipt');
    $ids = seedWeddingGraph(receiptPath: 'payments/venue.pdf');
    $other = seedWeddingGraph();

    expect(weddingRowCounts($ids['wedding']))->toBe([
        'weddings' => 1, 'categories' => 2, 'exchange_rates' => 1, 'revenues' => 1, 'gift_envelopes' => 2, 'expenses' => 2, 'expense_payments' => 2,
    ]);

    app(DeleteWedding::class)->handle(Wedding::query()->findOrFail($ids['wedding']));

    expect(weddingRowCounts($ids['wedding']))->each->toBe(0)
        ->and(DB::table('expense_payments')->whereIn('expense_id', [$ids['expense'], $ids['trashed_expense']])->count())->toBe(0)
        ->and(DB::table('users')->where('id', $ids['user'])->exists())->toBeTrue()
        ->and(weddingRowCounts($other['wedding'])['expense_payments'])->toBe(2);

    Storage::disk('receipts')->assertMissing('payments/venue.pdf');
});

it('does nothing when the wedding was already deleted by another request', function () {
    $ids = seedWeddingGraph();
    $wedding = Wedding::query()->findOrFail($ids['wedding']);
    app(DeleteWedding::class)->handle($wedding);

    app(DeleteWedding::class)->handle($wedding);

    expect(weddingRowCounts($ids['wedding']))->each->toBe(0);
});
