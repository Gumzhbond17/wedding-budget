<?php

namespace Database\Seeders;

use App\Actions\ExchangeRates\ManageExchangeRate;
use App\Actions\Expenses\ManageExpense;
use App\Actions\Expenses\ManageExpensePayment;
use App\Actions\GiftEnvelopes\ManageGiftEnvelope;
use App\Actions\Revenues\ManageRevenue;
use App\Actions\Weddings\CreateWedding;
use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Enums\GuestGroup;
use App\Enums\PaymentMethod;
use App\Enums\RevenueStatus;
use App\Models\Category;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Seeder;

/**
 * One realistic Vientiane wedding in LAK, USD and THB, covering every dashboard case:
 * received vs expected revenue, every expense status, payments at different rates,
 * unpaid expenses due within 30 days, and over-budget categories.
 *
 * Every record goes through the same actions as the API, so rate snapshots, cached payment
 * totals and derived statuses follow docs/business-rules.md exactly.
 *
 * Login: demo@example.com / password
 */
class DemoWeddingSeeder extends Seeder
{
    public function __construct(
        private readonly CreateWedding $createWedding,
        private readonly ManageExchangeRate $exchangeRates,
        private readonly ManageRevenue $revenues,
        private readonly ManageExpense $expenses,
        private readonly ManageExpensePayment $payments,
        private readonly ManageGiftEnvelope $giftEnvelopes,
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
        $this->seedGiftEnvelopes($wedding);
    }

    /**
     * Envelopes from guests who cannot come on the day and gave theirs early, in LAK (base).
     */
    private function seedGiftEnvelopes(Wedding $wedding): void
    {
        foreach ([
            ['Bounmy Sengdara', '1000000.00', GuestGroup::GroomFamily, '2026-11-02', 'Uncle, working in Pakse'],
            ['Khamla Inthavong', '500000.00', GuestGroup::BrideFamily, '2026-11-03', null],
            ['Somphone Keola', '300000.00', GuestGroup::Friends, '2026-11-05', 'University friend'],
            ['Vilay Chanthavong', '200000.00', GuestGroup::Colleagues, '2026-11-08', null],
            ['Noy Phommavong', '2000000.00', GuestGroup::Relatives, '2026-11-10', 'Cousin, abroad on the day'],
            ['Thongdy Sisouk', '150000.00', GuestGroup::Neighbors, '2026-11-12', null],
        ] as [$name, $amount, $group, $date, $notes]) {
            $this->giftEnvelopes->create($wedding, [
                'full_name' => $name,
                'amount' => $amount,
                'guest_group' => $group->value,
                'received_date' => $date,
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Rates as entered in Settings: 1 unit = n LAK.
     */
    private function seedExchangeRates(Wedding $wedding): void
    {
        foreach ([
            ['USD', '21650.00000000', '2026-08-01'],
            ['USD', '21850.00000000', '2026-10-01'],
            ['THB', '612.50000000', '2026-08-01'],
            ['THB', '628.40000000', '2026-10-01'],
        ] as [$from, $rate, $date]) {
            $this->exchangeRates->create($wedding, ['from_currency' => $from, 'rate' => $rate, 'effective_date' => $date]);
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

    /**
     * Rate snapshot by received_date, else expected_date.
     */
    private function seedRevenues(Wedding $wedding): void
    {
        foreach ([
            ['Family Contribution', "Groom's parents", '80000000.00', Currency::LAK, RevenueStatus::Received, '2026-09-01', '2026-09-15', PaymentMethod::BankTransfer],
            ['Family Contribution', "Bride's parents", '60000000.00', Currency::LAK, RevenueStatus::Expected, '2027-01-15', null, null],
            ['Personal Savings', 'Joint savings account', '3000.00', Currency::USD, RevenueStatus::Received, '2026-10-05', '2026-10-05', PaymentMethod::BankTransfer],
            ['Guest Gifts', 'Envelope gifts on the day (estimate)', '90000000.00', Currency::LAK, RevenueStatus::Expected, '2027-02-20', null, null],
            ['Sponsorship', 'Cousin working in Bangkok', '25000.00', Currency::THB, RevenueStatus::Expected, '2027-01-31', null, null],
            ['Loan', 'Village credit union', '30000000.00', Currency::LAK, RevenueStatus::Received, '2026-11-01', '2026-11-01', PaymentMethod::Cash],
        ] as [$category, $source, $amount, $currency, $status, $expected, $received, $method]) {
            $this->revenues->create($wedding, [
                'category_id' => $this->category($wedding, CategoryType::Revenue, $category)->id,
                'source' => $source,
                'amount' => $amount,
                'currency' => $currency->value,
                'status' => $status->value,
                'expected_date' => $expected,
                'received_date' => $received,
                'payment_method' => $method?->value,
            ]);
        }
    }

    /**
     * Expense rates are snapshotted on the seeding day; payment rates by paid_date.
     */
    private function seedExpenses(Wedding $wedding): void
    {
        $expenses = [
            // [category, description, vendor, currency, estimated, actual, user status, due date, payments[amount, date, method]]
            ['Venue', 'Banquet hall, 400 guests', 'Don Chan Palace', Currency::USD, '6500.00', '6800.00', ExpenseStatus::Booked, '2027-01-20', [
                ['2000.00', '2026-08-20', PaymentMethod::BankTransfer],
                ['2000.00', '2026-10-20', PaymentMethod::BankTransfer],
            ]],
            ['Catering', 'Dinner buffet, 400 guests', 'Kualao Catering', Currency::LAK, '120000000.00', null, ExpenseStatus::Booked, '2027-02-10', []],
            ['Attire', 'Sinh and suit tailoring', 'Phaxay Silk', Currency::THB, '35000.00', '35000.00', ExpenseStatus::Booked, '2026-10-15', [
                ['35000.00', '2026-10-10', PaymentMethod::BankTransfer],
            ]],
            ['Photography & Video', 'Pre-wedding and ceremony shoot', 'Lumiere Studio', Currency::LAK, '18000000.00', '18000000.00', ExpenseStatus::Booked, '2026-12-05', [
                ['5000000.00', '2026-09-01', PaymentMethod::Cash],
            ]],
            ['Decoration & Flowers', 'Baci and reception flowers', 'Champa Flowers', Currency::LAK, '12000000.00', null, ExpenseStatus::Planned, '2027-02-15', []],
            ['Music & Entertainment', 'Live band and MC', 'Mekong Sound', Currency::LAK, '9000000.00', '9500000.00', ExpenseStatus::Booked, '2026-12-10', []],
            ['Invitations', '500 printed invitations', 'Vientiane Print House', Currency::LAK, '4500000.00', '4200000.00', ExpenseStatus::Booked, '2026-11-05', [
                ['4200000.00', '2026-11-05', PaymentMethod::Cash],
            ]],
            ['Rings & Jewelry', 'Gold wedding rings', 'Yaowarat Gold', Currency::THB, '48000.00', '52000.00', ExpenseStatus::Booked, '2026-12-01', [
                ['30000.00', '2026-10-15', PaymentMethod::Cash],
            ]],
            ['Transportation', 'Wedding car rental', 'Lao Limousine', Currency::LAK, '3000000.00', null, ExpenseStatus::Planned, '2027-02-18', []],
            ['Ceremony', "Monks' offerings and Baci", null, Currency::LAK, '6000000.00', null, ExpenseStatus::Planned, '2027-02-19', []],
            ['Honeymoon', 'Luang Prabang, 4 nights', 'Mekong Riverside Hotel', Currency::USD, '1200.00', null, ExpenseStatus::Planned, '2027-02-25', []],
            ['Miscellaneous', 'Drone videography', 'SkyView Laos', Currency::LAK, '4000000.00', null, ExpenseStatus::Cancelled, null, []],
        ];

        foreach ($expenses as [$category, $description, $vendor, $currency, $estimated, $actual, $status, $due, $payments]) {
            $expense = $this->expenses->create($wedding, [
                'category_id' => $this->category($wedding, CategoryType::Expense, $category)->id,
                'description' => $description,
                'vendor_name' => $vendor,
                'currency' => $currency->value,
                'estimated_amount' => $estimated,
                'actual_amount' => $actual,
                'status' => $status->value,
                'due_date' => $due,
            ]);

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
