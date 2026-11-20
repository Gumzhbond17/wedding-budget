<?php

use App\Enums\BudgetHealth;
use App\Enums\Currency;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\GiftEnvelope;
use App\Models\Revenue;
use App\Models\Wedding;
use App\Services\DashboardSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00', DashboardSummaryService::TIMEZONE)));

it('computes the summary totals', function () {
    $dashboard = app(DashboardSummaryService::class)->summarize(dashboardFixture()['wedding']);

    expect($dashboard['as_of'])->toBe('2026-11-18')
        ->and($dashboard['base_currency'])->toBe(Currency::LAK)
        ->and($dashboard['summary'])->toBe([
            'budget_target' => '250000000.00',
            // Trashed revenue excluded.
            'revenue' => ['expected' => '50750000.00', 'received' => '143600000.00', 'total' => '194350000.00', 'gift_envelopes' => '0.00'],
            'expenses' => [
                // Cancelled E4 counts only its 1,000,000 forfeited deposit; trashed E6 not at all.
                'estimated' => '188075000.00',
                'actual' => '176845000.00',
                // Overpaid E5 counts what actually left: 12,100,000.
                'paid' => '130000000.00',
                'committed' => '197445000.00',
            ],
            'remaining_budget' => '73155000.00',   // 250,000,000 − 176,845,000
            'net_cash_position' => '13600000.00',  // 143,600,000 − 130,000,000
            'funding_gap' => '3095000.00',         // 197,445,000 − (50,750,000 + 143,600,000)
            'secured_gap' => '53845000.00',        // 197,445,000 − 143,600,000 (pledges ignored)
            'planned_total' => '200000000.00',     // Attire has no plan
            'unallocated_budget' => '50000000.00', // 250,000,000 − 200,000,000
        ]);
});

it('shows a negative funding gap and remaining budget when they are negative', function () {
    $wedding = Wedding::factory()->create(['budget_target' => '1000000.00']);
    Revenue::factory()->for($wedding)->create(['amount' => '9000000.00']);
    Expense::factory()->for($wedding)->invoiced('3000000.00')->create(['estimated_amount' => '3000000.00']);

    expect(app(DashboardSummaryService::class)->summarize($wedding)['summary'])
        ->funding_gap->toBe('-6000000.00')
        // The 9,000,000 is only pledged, so the secured gap still shows the full commitment.
        ->secured_gap->toBe('3000000.00')
        ->remaining_budget->toBe('-2000000.00');
});

it('shows a secured surplus once received money exceeds commitments', function () {
    $wedding = Wedding::factory()->create();
    Revenue::factory()->for($wedding)->received()->create(['amount' => '5000000.00']);
    Revenue::factory()->for($wedding)->create(['amount' => '2000000.00']);
    Expense::factory()->for($wedding)->create(['estimated_amount' => '4500000.00']);

    expect(app(DashboardSummaryService::class)->summarize($wedding)['summary'])
        ->funding_gap->toBe('-2500000.00')  // 4,500,000 − (2,000,000 + 5,000,000)
        ->secured_gap->toBe('-500000.00');  // 4,500,000 − 5,000,000
});

it('breaks totals down per original currency', function () {
    $dashboard = app(DashboardSummaryService::class)->summarize(dashboardFixture()['wedding']);
    $pair = fn (string $amount, string $base) => ['amount' => $amount, 'base' => $base];

    expect($dashboard['per_currency'])->toBe([
        [
            'currency' => Currency::LAK,
            'revenue' => ['expected' => $pair('20000000.00', '20000000.00'), 'received' => $pair('100000000.00', '100000000.00')],
            'expenses' => [
                'estimated' => $pair('93000000.00', '93000000.00'),
                'actual' => $pair('96000000.00', '96000000.00'),
                'paid' => $pair('96000000.00', '96000000.00'),
                'committed' => $pair('98000000.00', '98000000.00'),
            ],
        ],
        [
            'currency' => Currency::USD,
            'revenue' => ['expected' => $pair('0.00', '0.00'), 'received' => $pair('2000.00', '43600000.00')],
            'expenses' => [
                'estimated' => $pair('3500.00', '76475000.00'),
                'actual' => $pair('3700.00', '80845000.00'),
                // Paid at payment-day rates: 1,000 @ 21,900 + 550 @ 22,000.
                'paid' => $pair('1550.00', '34000000.00'),
                'committed' => $pair('3700.00', '80845000.00'),
            ],
        ],
        [
            'currency' => Currency::THB,
            'revenue' => ['expected' => $pair('50000.00', '30750000.00'), 'received' => $pair('0.00', '0.00')],
            'expenses' => [
                'estimated' => $pair('30000.00', '18600000.00'),
                'actual' => $pair('0.00', '0.00'),
                'paid' => $pair('0.00', '0.00'),
                'committed' => $pair('30000.00', '18600000.00'),
            ],
        ],
    ]);
});

it('compares each expense category with its plan', function () {
    $dashboard = app(DashboardSummaryService::class)->summarize(dashboardFixture()['wedding']);

    expect(collect($dashboard['categories'])->map(fn (array $row) => collect($row)->except('id')->all())->all())->toBe([
        ['name' => 'Attire', 'planned' => null, 'estimated' => '10925000.00', 'actual' => '10925000.00', 'paid' => '12100000.00',
            'committed' => '10925000.00', 'used_percent' => null, 'health' => null],
        // (69,920,000 + 2,000,000) / 80,000,000 = 89.9%
        ['name' => 'Catering', 'planned' => '80000000.00', 'estimated' => '67550000.00', 'actual' => '69920000.00', 'paid' => '21900000.00',
            'committed' => '71920000.00', 'used_percent' => '89.90', 'health' => BudgetHealth::Green],
        // Planned only, no expenses yet.
        ['name' => 'Music & Entertainment', 'planned' => '5000000.00', 'estimated' => '0.00', 'actual' => '0.00', 'paid' => '0.00',
            'committed' => '0.00', 'used_percent' => '0.00', 'health' => BudgetHealth::Green],
        // THB estimate 18,600,000 + cancelled deposit 1,000,000 = 130.67% of 15,000,000.
        ['name' => 'Photography & Video', 'planned' => '15000000.00', 'estimated' => '19600000.00', 'actual' => '1000000.00', 'paid' => '1000000.00',
            'committed' => '19600000.00', 'used_percent' => '130.67', 'health' => BudgetHealth::Red],
        // Trashed E6 excluded: 95,000,000 / 100,000,000.
        ['name' => 'Venue', 'planned' => '100000000.00', 'estimated' => '90000000.00', 'actual' => '95000000.00', 'paid' => '95000000.00',
            'committed' => '95000000.00', 'used_percent' => '95.00', 'health' => BudgetHealth::Amber],
    ]);
});

it('breaks committed expenses down by category, largest first', function () {
    $dashboard = app(DashboardSummaryService::class)->summarize(dashboardFixture()['wedding']);

    expect(collect($dashboard['expense_breakdown'])->map(fn (array $row) => [$row['name'], $row['committed'], $row['share_percent']])->all())->toBe([
        ['Venue', '95000000.00', '48.11'],
        ['Catering', '71920000.00', '36.43'],
        ['Photography & Video', '19600000.00', '9.93'],
        ['Attire', '10925000.00', '5.53'],
    ]);
});

it('orders equal slices by name', function () {
    $wedding = Wedding::factory()->create();
    foreach (['Venue', 'Catering'] as $name) {
        $category = Category::factory()->expense()->for($wedding)->create(['name' => $name]);
        Expense::factory()->for($wedding)->for($category)->create(['estimated_amount' => '1000000.00']);
    }

    expect(array_column(app(DashboardSummaryService::class)->summarize($wedding)['expense_breakdown'], 'name'))->toBe(['Catering', 'Venue']);
});

it('lists spending in categories without a plan apart from over-budget ones', function () {
    $wedding = Wedding::factory()->create();
    $unplanned = Category::factory()->expense()->for($wedding)->create(['name' => 'Attire', 'planned_amount' => null]);
    $zeroPlan = Category::factory()->expense()->for($wedding)->create(['name' => 'Gifts', 'planned_amount' => '0.00']);
    Category::factory()->expense()->for($wedding)->create(['name' => 'Ceremony', 'planned_amount' => null]);
    Expense::factory()->for($wedding)->for($unplanned)->create(['estimated_amount' => '2500000.00']);
    Expense::factory()->for($wedding)->for($zeroPlan)->create(['estimated_amount' => '500000.00']);

    $dashboard = app(DashboardSummaryService::class)->summarize($wedding);

    // Ceremony has neither a plan nor spending; Gifts has a plan of 0, so it is over budget instead.
    expect($dashboard['unplanned_spending'])->toBe([['id' => $unplanned->id, 'name' => 'Attire', 'committed' => '2500000.00']])
        ->and(collect($dashboard['categories'])->firstWhere('name', 'Attire')['health'])->toBeNull()
        ->and(collect($dashboard['categories'])->firstWhere('name', 'Gifts')['health'])->toBe(BudgetHealth::Red);
});

it('builds a continuous monthly cash flow from received revenue and payments', function () {
    $dashboard = app(DashboardSummaryService::class)->summarize(dashboardFixture()['wedding']);

    expect($dashboard['cash_flow'])->toBe([
        // Forfeited deposit of the cancelled E4.
        ['month' => '2026-07', 'inflow' => '0.00', 'outflow' => '1000000.00', 'net' => '-1000000.00', 'cumulative' => '-1000000.00'],
        // No movement: still listed so the chart has no gap.
        ['month' => '2026-08', 'inflow' => '0.00', 'outflow' => '0.00', 'net' => '0.00', 'cumulative' => '-1000000.00'],
        ['month' => '2026-09', 'inflow' => '100000000.00', 'outflow' => '0.00', 'net' => '100000000.00', 'cumulative' => '99000000.00'],
        ['month' => '2026-10', 'inflow' => '43600000.00', 'outflow' => '95000000.00', 'net' => '-51400000.00', 'cumulative' => '47600000.00'],
        // E2 21,900,000 + E5 12,100,000; trashed E6's payment and trashed revenue excluded.
        ['month' => '2026-11', 'inflow' => '0.00', 'outflow' => '34000000.00', 'net' => '-34000000.00', 'cumulative' => '13600000.00'],
    ])->and(end($dashboard['cash_flow'])['cumulative'])->toBe($dashboard['summary']['net_cash_position']);
});

it('counts gift envelopes as received revenue in the base currency', function () {
    $wedding = dashboardFixture()['wedding'];
    GiftEnvelope::factory()->for($wedding)->create(['amount' => '500000.00', 'received_date' => '2026-11-05']);
    GiftEnvelope::factory()->for($wedding)->create(['amount' => '1500000.00', 'received_date' => '2026-12-20']);
    GiftEnvelope::factory()->for($wedding)->create(['amount' => '9000000.00', 'received_date' => '2026-11-05'])->delete();
    GiftEnvelope::factory()->create(['amount' => '7000000.00']); // another wedding

    $dashboard = app(DashboardSummaryService::class)->summarize($wedding);
    $lak = collect($dashboard['per_currency'])->firstWhere('currency', Currency::LAK);

    // 2,000,000 on top of the fixture's received 143,600,000; the trashed envelope is excluded.
    expect($dashboard['summary']['revenue'])->toBe([
        'expected' => '50750000.00', 'received' => '145600000.00', 'total' => '196350000.00', 'gift_envelopes' => '2000000.00',
    ])
        ->and($dashboard['summary']['net_cash_position'])->toBe('15600000.00')
        ->and($dashboard['summary']['secured_gap'])->toBe('51845000.00')
        ->and($lak['revenue']['received'])->toBe(['amount' => '102000000.00', 'base' => '102000000.00'])
        ->and(collect($dashboard['cash_flow'])->pluck('inflow', 'month')->only(['2026-11', '2026-12'])->all())
        ->toBe(['2026-11' => '500000.00', '2026-12' => '1500000.00'])
        ->and(end($dashboard['cash_flow'])['cumulative'])->toBe('15600000.00');
});

it('lists unpaid balances due in the next 30 days in the expense currency', function () {
    ['wedding' => $wedding, 'expenses' => $expenses] = dashboardFixture();

    $upcoming = app(DashboardSummaryService::class)->summarize($wedding)['upcoming_payments'];

    // Not listed: E4 cancelled, E5 overpaid (nothing left), E6 trashed, E7 due in 31 days, E1 paid.
    expect($upcoming->map(fn (Expense $expense) => [
        $expense->id, $expense->due_date->toDateString(), $expense->outstanding_amount, $expense->currency, $expense->actual_amount === null, $expense->category_name,
    ])->all())->toBe([
        [$expenses['e2']->id, '2026-11-18', '2200.00', Currency::USD, false, 'Catering'],
        [$expenses['e3']->id, '2026-12-18', '30000.00', Currency::THB, true, 'Photography & Video'],
    ]);
});

it('uses the Vientiane calendar for today and the 30-day window', function () {
    $serverTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');

    try {
        // 20:00 UTC on the 18th is already 03:00 on the 19th in Vientiane (UTC+7).
        $this->travelTo(CarbonImmutable::parse('2026-11-18 20:00', 'UTC'));
        $wedding = Wedding::factory()->create();
        $due = fn (string $date) => Expense::factory()->for($wedding)->create(['due_date' => $date, 'description' => $date]);
        $due('2026-11-18');
        $due('2026-11-19');
        $due('2026-12-19');
        $due('2026-12-20');

        $dashboard = app(DashboardSummaryService::class)->summarize($wedding);

        expect($dashboard['as_of'])->toBe('2026-11-19')
            ->and($dashboard['upcoming_payments']->pluck('description')->all())->toBe(['2026-11-19', '2026-12-19']);
    } finally {
        date_default_timezone_set($serverTimezone);
    }
});

it('returns zeros and empty lists for a new wedding', function () {
    $wedding = Wedding::factory()->withDefaultCategories()->create(['budget_target' => '0.00']);

    $dashboard = app(DashboardSummaryService::class)->summarize($wedding);

    expect($dashboard['summary']['revenue'])->toBe(['expected' => '0.00', 'received' => '0.00', 'total' => '0.00', 'gift_envelopes' => '0.00'])
        ->and($dashboard['summary']['expenses'])->toBe(['estimated' => '0.00', 'actual' => '0.00', 'paid' => '0.00', 'committed' => '0.00'])
        ->and($dashboard['summary']['funding_gap'])->toBe('0.00')
        ->and($dashboard['per_currency'])->toHaveCount(3)
        ->and($dashboard['categories'])->toHaveCount(12)
        ->and($dashboard['expense_breakdown'])->toBe([])
        ->and($dashboard['cash_flow'])->toBe([])
        ->and($dashboard['upcoming_payments'])->toBeEmpty();
});

it('ignores other weddings', function () {
    $wedding = Wedding::factory()->create();
    dashboardFixture();

    expect(app(DashboardSummaryService::class)->summarize($wedding)['summary']['expenses']['committed'])->toBe('0.00');
});

it('runs a fixed number of queries whatever the number of records', function () {
    $wedding = dashboardFixture()['wedding'];
    $service = app(DashboardSummaryService::class);

    $count = function () use ($service, $wedding): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->summarize($wedding);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($count())->toBe(DashboardSummaryService::QUERY_COUNT);

    Expense::factory()->for($wedding)->count(20)->create(['due_date' => '2026-11-30']);
    Revenue::factory()->for($wedding)->received()->count(20)->create();
    GiftEnvelope::factory()->for($wedding)->count(20)->create();

    expect($count())->toBe(DashboardSummaryService::QUERY_COUNT)->toBe(5);
});

/*
 * Both moments fall on 30 November in UTC (16:30 and 17:30), so a server-clock "today" would
 * give the same answer twice. In Vientiane (UTC+7) they straddle midnight and a month boundary.
 * "This month" is the cash-flow row of the as_of month, which the dashboard highlights.
 */
it('switches today, the 30-day window and this month at Vientiane midnight', function (string $vientianeTime, string $today, array $upcoming, string $outflowThisMonth) {
    $serverTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');

    try {
        $this->travelTo(CarbonImmutable::parse($vientianeTime, DashboardSummaryService::TIMEZONE));
        $wedding = Wedding::factory()->create();
        foreach (['2026-11-29', '2026-11-30', '2026-12-01', '2026-12-30', '2026-12-31', '2027-01-01'] as $date) {
            Expense::factory()->for($wedding)->create(['due_date' => $date, 'description' => $date]);
        }
        $paidUpFront = Expense::factory()->for($wedding)->create(['due_date' => null, 'estimated_amount' => '10000000.00']);
        ExpensePayment::factory()->for($paidUpFront)->create(['amount' => '1500000.00', 'paid_date' => '2026-11-30']);
        ExpensePayment::factory()->for($paidUpFront)->create(['amount' => '2500000.00', 'paid_date' => '2026-12-01']);

        $dashboard = app(DashboardSummaryService::class)->summarize($wedding);
        $thisMonth = collect($dashboard['cash_flow'])->firstWhere('month', substr($dashboard['as_of'], 0, 7));

        expect($dashboard['as_of'])->toBe($today)
            ->and($dashboard['upcoming_payments']->pluck('description')->all())->toBe($upcoming)
            ->and($thisMonth['outflow'])->toBe($outflowThisMonth);
    } finally {
        date_default_timezone_set($serverTimezone);
    }
})->with([
    '23:30 on the last day of the month' => ['2026-11-30 23:30', '2026-11-30', ['2026-11-30', '2026-12-01', '2026-12-30'], '1500000.00'],
    '00:30 on the first day of the next month' => ['2026-12-01 00:30', '2026-12-01', ['2026-12-01', '2026-12-30', '2026-12-31'], '2500000.00'],
]);
