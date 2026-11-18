<?php

namespace Database\Seeders;

use App\Actions\Expenses\ManageExpensePayment;
use App\Actions\Weddings\CreateWedding;
use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Enums\RevenueStatus;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Seeder;

/**
 * One realistic Vientiane wedding in LAK, USD and THB, covering every dashboard case:
 * received vs expected revenue, every expense status, payments at different rates,
 * unpaid expenses due within 30 days, and over-budget categories.
 *
 * Login: demo@example.com / password
 */
class DemoWeddingSeeder extends Seeder
{
    /** Rate snapshots, as entered in Settings: 1 unit = n LAK. */
    private const string USD_AUG = '21650.00000000';

    private const string USD_OCT = '21850.00000000';

    private const string THB_AUG = '612.50000000';

    private const string THB_OCT = '628.40000000';

    private const string LAK = '1.00000000';

    public function __construct(
        private readonly CreateWedding $createWedding,
        private readonly ManageExpensePayment $payments,
    ) {}

    public function run(): void
    {
        $user = User::factory()->create(['name' => 'Vanh Phommachanh', 'email' => 'demo@example.com']);

        $wedding = $this->createWedding->handle($user, [
            'name' => 'Vanh & Noy',
            'wedding_date' => '2027-02-20',
            'base_currency' => Currency::LAK->value,
            'budget_target' => '350000000.00',
            'notes' => 'Baci in the morning, reception at the hotel in the evening.',
        ]);

        $this->seedExchangeRates($wedding);
        $this->seedPlannedAmounts($wedding);
        $this->seedRevenues($wedding);
        $this->seedExpenses($wedding);
    }

    private function seedExchangeRates(Wedding $wedding): void
    {
        foreach ([
            [Currency::USD, self::USD_AUG, '2026-08-01'],
            [Currency::USD, self::USD_OCT, '2026-10-01'],
            [Currency::THB, self::THB_AUG, '2026-08-01'],
            [Currency::THB, self::THB_OCT, '2026-10-01'],
        ] as [$from, $rate, $date]) {
            ExchangeRate::factory()->for($wedding)->create([
                'from_currency' => $from,
                'to_currency' => Currency::LAK,
                'rate' => $rate,
                'effective_date' => $date,
            ]);
        }
    }

    /**
     * Venue and Rings & Jewelry are set below their actual cost on purpose (over budget).
     */
    private function seedPlannedAmounts(Wedding $wedding): void
    {
        foreach ([
            'Venue' => '140000000.00',
            'Catering' => '125000000.00',
            'Attire' => '25000000.00',
            'Photography & Video' => '20000000.00',
            'Decoration & Flowers' => '15000000.00',
            'Music & Entertainment' => '10000000.00',
            'Invitations' => '5000000.00',
            'Rings & Jewelry' => '30000000.00',
            'Transportation' => '4000000.00',
            'Ceremony' => '8000000.00',
            'Honeymoon' => '30000000.00',
        ] as $name => $planned) {
            $this->category($wedding, CategoryType::Expense, $name)->update(['planned_amount' => $planned]);
        }
    }

    private function seedRevenues(Wedding $wedding): void
    {
        foreach ([
            ['Family Contribution', "Groom's parents", '80000000.00', Currency::LAK, self::LAK, RevenueStatus::Received, '2026-09-01', '2026-09-15', PaymentMethod::BankTransfer],
            ['Family Contribution', "Bride's parents", '60000000.00', Currency::LAK, self::LAK, RevenueStatus::Expected, '2027-01-15', null, null],
            ['Personal Savings', 'Joint savings account', '3000.00', Currency::USD, self::USD_OCT, RevenueStatus::Received, '2026-10-05', '2026-10-05', PaymentMethod::BankTransfer],
            ['Guest Gifts', 'Envelope gifts on the day (estimate)', '90000000.00', Currency::LAK, self::LAK, RevenueStatus::Expected, '2027-02-20', null, null],
            ['Sponsorship', 'Cousin working in Bangkok', '25000.00', Currency::THB, self::THB_OCT, RevenueStatus::Expected, '2027-01-31', null, null],
            ['Loan', 'Village credit union', '30000000.00', Currency::LAK, self::LAK, RevenueStatus::Received, '2026-11-01', '2026-11-01', PaymentMethod::Cash],
        ] as [$category, $source, $amount, $currency, $rate, $status, $expected, $received, $method]) {
            Revenue::factory()->for($wedding)->create([
                'category_id' => $this->category($wedding, CategoryType::Revenue, $category)->id,
                'source' => $source,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'status' => $status,
                'expected_date' => $expected,
                'received_date' => $received,
                'payment_method' => $method,
            ]);
        }
    }

    private function seedExpenses(Wedding $wedding): void
    {
        $expenses = [
            // [category, description, vendor, currency, rate, estimated, actual, user status, due date, payments[amount, date, method]; payment rates come from the rate table]
            ['Venue', 'Banquet hall, 400 guests', 'Don Chan Palace', Currency::USD, self::USD_OCT, '6500.00', '6800.00', ExpenseStatus::Booked, '2027-01-20', [
                ['2000.00', '2026-08-20', PaymentMethod::BankTransfer],
                ['2000.00', '2026-10-20', PaymentMethod::BankTransfer],
            ]],
            ['Catering', 'Dinner buffet, 400 guests', 'Kualao Catering', Currency::LAK, self::LAK, '120000000.00', null, ExpenseStatus::Booked, '2027-02-10', []],
            ['Attire', 'Sinh and suit tailoring', 'Phaxay Silk', Currency::THB, self::THB_OCT, '35000.00', '35000.00', ExpenseStatus::Booked, '2026-10-15', [
                ['35000.00', '2026-10-10', PaymentMethod::BankTransfer],
            ]],
            ['Photography & Video', 'Pre-wedding and ceremony shoot', 'Lumiere Studio', Currency::LAK, self::LAK, '18000000.00', '18000000.00', ExpenseStatus::Booked, '2026-12-05', [
                ['5000000.00', '2026-09-01', PaymentMethod::Cash],
            ]],
            ['Decoration & Flowers', 'Baci and reception flowers', 'Champa Flowers', Currency::LAK, self::LAK, '12000000.00', null, ExpenseStatus::Planned, '2027-02-15', []],
            ['Music & Entertainment', 'Live band and MC', 'Mekong Sound', Currency::LAK, self::LAK, '9000000.00', '9500000.00', ExpenseStatus::Booked, '2026-12-10', []],
            ['Invitations', '500 printed invitations', 'Vientiane Print House', Currency::LAK, self::LAK, '4500000.00', '4200000.00', ExpenseStatus::Booked, '2026-11-05', [
                ['4200000.00', '2026-11-05', PaymentMethod::Cash],
            ]],
            ['Rings & Jewelry', 'Gold wedding rings', 'Yaowarat Gold', Currency::THB, self::THB_OCT, '48000.00', '52000.00', ExpenseStatus::Booked, '2026-12-01', [
                ['30000.00', '2026-10-15', PaymentMethod::Cash],
            ]],
            ['Transportation', 'Wedding car rental', 'Lao Limousine', Currency::LAK, self::LAK, '3000000.00', null, ExpenseStatus::Planned, '2027-02-18', []],
            ['Ceremony', "Monks' offerings and Baci", null, Currency::LAK, self::LAK, '6000000.00', null, ExpenseStatus::Planned, '2027-02-19', []],
            ['Honeymoon', 'Luang Prabang, 4 nights', 'Mekong Riverside Hotel', Currency::USD, self::USD_OCT, '1200.00', null, ExpenseStatus::Planned, '2027-02-25', []],
            ['Miscellaneous', 'Drone videography', 'SkyView Laos', Currency::LAK, self::LAK, '4000000.00', null, ExpenseStatus::Cancelled, null, []],
        ];

        foreach ($expenses as [$category, $description, $vendor, $currency, $rate, $estimated, $actual, $status, $due, $payments]) {
            $expense = Expense::factory()->for($wedding)->create([
                'category_id' => $this->category($wedding, CategoryType::Expense, $category)->id,
                'description' => $description,
                'vendor_name' => $vendor,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'estimated_amount' => $estimated,
                'actual_amount' => $actual,
                'status' => $status,
                'due_date' => $due,
            ]);

            // Same path as the API: rate snapshot by paid_date, cached totals and status refreshed.
            foreach ($payments as [$amount, $paidDate, $method]) {
                $this->payments->create($expense, [
                    'amount' => $amount,
                    'paid_date' => $paidDate,
                    'payment_method' => $method->value,
                ]);
            }
        }
    }

    private function category(Wedding $wedding, CategoryType $type, string $name): Category
    {
        return $wedding->categories()->ofType($type)->where('name', $name)->sole();
    }
}
