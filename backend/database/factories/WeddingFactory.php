<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\WeddingStatus;
use App\Models\User;
use App\Models\Wedding;
use Database\Factories\Concerns\ConvertsToBaseAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wedding>
 */
class WeddingFactory extends Factory
{
    use ConvertsToBaseAmount;

    /**
     * Common Lao given names, for demo data that reads like the target audience.
     */
    private const array NAMES = [
        'Souksavanh', 'Khamla', 'Phonesavanh', 'Noy', 'Vanh', 'Malaythong', 'Bounmy',
        'Sengdao', 'Kongkeo', 'Manivanh', 'Somphone', 'Vilayphone', 'Anousone', 'Chanthavong',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$first, $second] = fake()->randomElements(self::NAMES, 2);

        return [
            'user_id' => User::factory(),
            'name' => "{$first} & {$second}",
            'wedding_date' => fake()->dateTimeBetween('+3 months', '+12 months')->format('Y-m-d'),
            'status' => WeddingStatus::Planning,
            'base_currency' => Currency::LAK,
            'budget_target' => $this->money(150_000_000, 400_000_000, 5_000_000),
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeddingStatus::Completed,
            'wedding_date' => fake()->dateTimeBetween('-12 months', '-1 week')->format('Y-m-d'),
        ]);
    }

    /**
     * Same result as App\Actions\Weddings\CreateWedding: the wedding owns the default categories.
     */
    public function withDefaultCategories(): static
    {
        return $this->afterCreating(fn (Wedding $wedding) => $wedding->createDefaultCategories());
    }
}
