<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpensePayment;
use Database\Factories\Concerns\ConvertsToBaseAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpensePayment>
 */
class ExpensePaymentFactory extends Factory
{
    use ConvertsToBaseAmount;

    /**
     * Define the model's default state.
     *
     * currency and exchange_rate default to the expense's own values: the currency must
     * match (composite FK); the rate is a fresh snapshot in real use and can be overridden.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory()->invoiced(),
            'amount' => $this->money(500_000, 10_000_000, 100_000),
            'currency' => fn (array $attributes) => Expense::query()->whereKey($attributes['expense_id'])->value('currency'),
            'exchange_rate' => fn (array $attributes) => Expense::query()->whereKey($attributes['expense_id'])->value('exchange_rate'),
            'base_amount' => fn (array $attributes) => $this->toBaseAmount($attributes['amount'], $attributes['exchange_rate']),
            'paid_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'notes' => null,
        ];
    }

    /**
     * Keep the expense's cached sums and derived status in line with its payments,
     * exactly as the payment service does in production.
     */
    public function configure(): static
    {
        return $this->afterCreating(
            fn (ExpensePayment $payment) => Expense::query()->findOrFail($payment->expense_id)->refreshPaymentTotals(),
        );
    }
}
