<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Wedding;
use BcMath\Number;
use Database\Factories\Concerns\ConvertsToBaseAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    use ConvertsToBaseAmount;

    /**
     * Define the model's default state: a planned LAK expense with no invoice yet.
     *
     * Closures run in key order with earlier attributes resolved, so the category is
     * created inside the same wedding (composite FK) and every base_* column follows
     * the amounts and exchange_rate, including values overridden by a state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'category_id' => fn (array $attributes) => Category::factory()->expense()
                ->create(['wedding_id' => $attributes['wedding_id']]),
            'description' => fake()->randomElement(['Deposit', 'Full package', 'Rental', 'Booking', 'Order']),
            'vendor_name' => fake()->company(),
            'currency' => Currency::LAK,
            'exchange_rate' => '1.00000000',
            'estimated_amount' => $this->money(1_000_000, 50_000_000, 500_000),
            'actual_amount' => null,
            'base_estimated_amount' => fn (array $attributes) => $this->toBaseAmount($attributes['estimated_amount'], $attributes['exchange_rate']),
            'base_actual_amount' => fn (array $attributes) => $this->toBaseAmount($attributes['actual_amount'], $attributes['exchange_rate']),
            'paid_amount' => '0.00',
            'base_paid_amount' => '0.00',
            'status' => ExpenseStatus::Planned,
            'due_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    /**
     * Amounts in a foreign currency with the rate snapshot used to convert them.
     */
    public function inCurrency(Currency $currency, string $exchangeRate, string $estimatedAmount): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'estimated_amount' => $estimatedAmount,
        ]);
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ExpenseStatus::Booked]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ExpenseStatus::Cancelled]);
    }

    /**
     * Invoice received: the actual amount is known (defaults to the estimate).
     */
    public function invoiced(?string $actualAmount = null): static
    {
        return $this->booked()->state(fn (array $attributes) => [
            'actual_amount' => $actualAmount ?? $attributes['estimated_amount'],
        ]);
    }

    /**
     * Invoiced and paid in full by one payment; totals and status come from the payment.
     */
    public function paid(): static
    {
        return $this->invoiced()->afterCreating(
            fn (Expense $expense) => ExpensePayment::factory()->for($expense)->create(['amount' => $expense->actual_amount]),
        );
    }

    /**
     * Invoiced with a 30% deposit paid.
     */
    public function partiallyPaid(): static
    {
        return $this->invoiced()->afterCreating(fn (Expense $expense) => ExpensePayment::factory()->for($expense)->create([
            'amount' => (string) (new Number($expense->actual_amount) * new Number('0.3'))->round(2),
        ]));
    }
}
