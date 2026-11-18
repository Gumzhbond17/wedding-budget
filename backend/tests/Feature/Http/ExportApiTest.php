<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Revenue;
use App\Models\Wedding;
use App\Services\RecordsCsvExporter;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => $this->travelTo('2026-11-18 09:00'));

/**
 * @return list<list<string>> CSV rows, after checking the UTF-8 BOM
 */
function csvRows(TestResponse $response): array
{
    $content = $response->streamedContent();
    expect($content)->toStartWith("\u{FEFF}");

    return array_map(
        fn (string $line) => str_getcsv($line, ',', '"', ''),
        explode("\r\n", rtrim(substr($content, 3), "\r\n")),
    );
}

it('exports revenues with original amount, currency, rate and base amount', function () {
    $wedding = Wedding::factory()->create();
    $category = Category::factory()->revenue()->for($wedding)->create(['name' => 'ຂອງຂວັນ']);
    $revenue = Revenue::factory()->for($wedding)->for($category)->inCurrency(Currency::USD, '21850.00000000', '1500.00')
        ->create(['source' => 'ພໍ່ແມ່ເຈົ້າບ່າວ', 'expected_date' => '2026-12-01', 'notes' => null]);
    Revenue::factory()->for($wedding)->create()->delete();
    Revenue::factory()->create(); // another wedding

    $response = $this->actingAs($wedding->user)->get(route('v1.weddings.exports.revenues', $wedding))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', "attachment; filename=wedding-{$wedding->id}-revenues-2026-11-18.csv")
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect(csvRows($response))->toBe([
        ['ID', 'Category', 'Source', 'Status', 'Expected date', 'Received date', 'Payment method',
            'Amount', 'Currency', 'Exchange rate', 'Base amount (LAK)', 'Notes'],
        [(string) $revenue->id, 'ຂອງຂວັນ', 'ພໍ່ແມ່ເຈົ້າບ່າວ', 'expected', '2026-12-01', '', '',
            '1500.00', 'USD', '21850.00000000', '32775000.00', ''],
    ]);
});

it('exports expenses with original and base amounts and the outstanding balance', function () {
    $wedding = Wedding::factory()->create();
    $category = Category::factory()->expense()->for($wedding)->create(['name' => 'Venue']);
    $expense = Expense::factory()->for($wedding)->for($category)->inCurrency(Currency::THB, '620.00000000', '30000.00')
        ->invoiced('32000.00')
        ->create(['description' => 'ຫ້ອງຈັດງານ', 'vendor_name' => 'Don Chan Palace', 'due_date' => '2026-12-05', 'notes' => null]);
    ExpensePayment::factory()->for($expense)->create(['amount' => '10000.00', 'exchange_rate' => '615.00000000']);
    Expense::factory()->for($wedding)->create()->delete();

    $rows = csvRows($this->actingAs($wedding->user)->get(route('v1.weddings.exports.expenses', $wedding))->assertOk());

    expect($rows)->toBe([
        ['ID', 'Category', 'Description', 'Vendor', 'Status', 'Due date',
            'Currency', 'Exchange rate', 'Estimated amount', 'Actual amount', 'Paid amount', 'Outstanding amount', 'Outstanding basis',
            'Base estimated (LAK)', 'Base actual (LAK)', 'Base paid (LAK)', 'Notes'],
        [(string) $expense->id, 'Venue', 'ຫ້ອງຈັດງານ', 'Don Chan Palace', 'partially_paid', '2026-12-05',
            'THB', '620.00000000', '30000.00', '32000.00', '10000.00', '22000.00', 'actual',
            '18600000.00', '19840000.00', '6150000.00', ''],
    ]);
});

it('neutralizes spreadsheet formulas in text cells', function () {
    $wedding = Wedding::factory()->create();
    Expense::factory()->for($wedding)->create([
        'description' => '=HYPERLINK("http://evil.example","Click")',
        'vendor_name' => '@SUM(1+1)*cmd|\' /C calc\'!A0',
        'notes' => '+1+2',
    ]);
    Expense::factory()->for($wedding)->create(['description' => '-2+3', 'vendor_name' => "\tTabbed", 'notes' => 'Plain = safe']);

    $rows = csvRows($this->actingAs($wedding->user)->get(route('v1.weddings.exports.expenses', $wedding)));

    expect([$rows[1][2], $rows[1][3], $rows[1][16]])->toBe(['\'=HYPERLINK("http://evil.example","Click")', '\'@SUM(1+1)*cmd|\' /C calc\'!A0', "'+1+2"])
        ->and([$rows[2][2], $rows[2][3], $rows[2][16]])->toBe(["'-2+3", "'\tTabbed", 'Plain = safe']);
});

it('escapes only cells that start with a formula character', function (string $cell, string $expected) {
    expect(RecordsCsvExporter::escape($cell))->toBe($expected);
})->with([
    ['=1+1', "'=1+1"],
    ['+1', "'+1"],
    ['-1', "'-1"],
    ['@A1', "'@A1"],
    ["\r=1", "'\r=1"],
    ['1500.00', '1500.00'],
    ['a=b', 'a=b'],
    ['', ''],
]);

it('streams large exports without changing the output', function () {
    $wedding = Wedding::factory()->create();
    $category = Category::factory()->revenue()->for($wedding)->create();
    Revenue::factory()->for($wedding)->for($category)->count(1100)->create();

    $rows = csvRows($this->actingAs($wedding->user)->get(route('v1.weddings.exports.revenues', $wedding)));

    expect($rows)->toHaveCount(1101)
        ->and(array_column(array_slice($rows, 1), 0))->toBe($wedding->revenues()->orderBy('id')->pluck('id')->map(fn (int $id) => (string) $id)->all());
});

it('rate limits exports per user', function () {
    $wedding = Wedding::factory()->create();
    $this->actingAs($wedding->user);

    foreach (range(1, 10) as $attempt) {
        $this->get(route('v1.weddings.exports.expenses', $wedding))->assertOk();
    }

    $this->get(route('v1.weddings.exports.revenues', $wedding))->assertTooManyRequests();
});
