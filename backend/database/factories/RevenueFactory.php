<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\RevenueStatus;
use App\Models\Category;
use App\Models\Revenue;
use App\Models\Wedding;
use Database\Factories\Concerns\ConvertsToBaseAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revenue>
 */
class RevenueFactory extends Factory
{
    use ConvertsToBaseAmount;

    /**
     * Define the model's default state: an expected LAK contribution.
     *
     * Closures run in key order with earlier attributes resolved, so the category is
     * created inside the same wedding (composite FK) and base_amount follows any
     * amount or exchange_rate overridden by a state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'category_id' => fn (array $attributes) => Category::factory()->revenue()
                ->create(['wedding_id' => $attributes['wedding_id']]),
            'source' => fake()->randomElement(['Groom\'s parents', 'Bride\'s parents', 'Uncle Bounmy', 'Office colleagues', 'Savings account']),
            'amount' => $this->money(2_000_000, 60_000_000, 500_000),
            'currency' => Currency::LAK,
            'exchange_rate' => '1.00000000',
            'base_amount' => fn (array $attributes) => $this->toBaseAmount($attributes['amount'], $attributes['exchange_rate']),
            'status' => RevenueStatus::Expected,
            'expected_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'received_date' => null,
            'payment_method' => null,
            'notes' => null,
        ];
    }

    /**
     * Amount in a foreign currency with the rate snapshot used to convert it.
     */
    public function inCurrency(Currency $currency, string $exchangeRate, string $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'amount' => $amount,
        ]);
    }

    public function received(?PaymentMethod $method = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RevenueStatus::Received,
            'received_date' => fake()->dateTimeBetween('-4 months', 'now')->format('Y-m-d'),
            'payment_method' => $method ?? fake()->randomElement(PaymentMethod::cases()),
        ]);
    }
}
