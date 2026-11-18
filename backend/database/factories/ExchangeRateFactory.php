<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state: USD -> LAK around late-2026 market levels.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'from_currency' => Currency::USD,
            'to_currency' => Currency::LAK,
            'rate' => fake()->numberBetween(21_400, 22_200).'.00000000',
            'effective_date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
        ];
    }

    public function thbToLak(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_currency' => Currency::THB,
            'to_currency' => Currency::LAK,
            'rate' => fake()->numberBetween(600, 680).'.'.fake()->numerify('##').'000000',
        ]);
    }
}
