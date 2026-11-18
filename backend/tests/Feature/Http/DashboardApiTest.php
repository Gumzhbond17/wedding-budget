<?php

use App\Models\Wedding;
use App\Services\DashboardSummaryService;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00', DashboardSummaryService::TIMEZONE)));

it('returns the dashboard with every amount tagged with its currency', function () {
    ['wedding' => $wedding, 'expenses' => $expenses] = dashboardFixture();

    $this->actingAs($wedding->user)->getJson(route('v1.weddings.dashboard', $wedding))
        ->assertOk()
        ->assertJsonPath('data.as_of', '2026-11-18')
        ->assertJsonPath('data.timezone', 'Asia/Vientiane')
        ->assertJsonPath('data.base_currency', 'LAK')
        ->assertJsonPath('data.summary.budget_target', ['amount' => '250000000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.revenue.received', ['amount' => '143600000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.expenses.committed', ['amount' => '197445000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.funding_gap', ['amount' => '3095000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.secured_gap', ['amount' => '53845000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.unallocated_budget', ['amount' => '50000000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.per_currency.1.expenses.paid', [
            'amount' => ['amount' => '1550.00', 'currency' => 'USD'],
            'base' => ['amount' => '34000000.00', 'currency' => 'LAK'],
        ])
        ->assertJsonPath('data.categories.0.planned', null)
        ->assertJsonPath('data.categories.3.health', 'red')
        ->assertJsonPath('data.categories.3.used_percent', '130.67')
        ->assertJsonPath('data.expense_breakdown.3.share_percent', '48.11')
        ->assertJsonPath('data.cash_flow.4.cumulative', ['amount' => '13600000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.upcoming_payments', [
            [
                'expense_id' => $expenses['e2']->id,
                'description' => $expenses['e2']->description,
                'vendor_name' => $expenses['e2']->vendor_name,
                'category' => ['id' => $expenses['e2']->category_id, 'name' => 'Catering'],
                'status' => 'partially_paid',
                'due_date' => '2026-11-18',
                'outstanding_amount' => ['amount' => '2200.00', 'currency' => 'USD', 'basis' => 'actual'],
            ],
            [
                'expense_id' => $expenses['e3']->id,
                'description' => $expenses['e3']->description,
                'vendor_name' => $expenses['e3']->vendor_name,
                'category' => ['id' => $expenses['e3']->category_id, 'name' => 'Photography & Video'],
                'status' => 'booked',
                'due_date' => '2026-12-18',
                'outstanding_amount' => ['amount' => '30000.00', 'currency' => 'THB', 'basis' => 'estimated'],
            ],
        ]);
});

it('serves the dashboard for an empty wedding', function () {
    $wedding = Wedding::factory()->create();

    $this->actingAs($wedding->user)->getJson(route('v1.weddings.dashboard', $wedding))
        ->assertOk()
        ->assertJsonPath('data.cash_flow', [])
        ->assertJsonPath('data.upcoming_payments', [])
        ->assertJsonCount(3, 'data.per_currency');
});
