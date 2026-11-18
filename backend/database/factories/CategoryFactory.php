<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Wedding;
use Database\Factories\Concerns\ConvertsToBaseAmount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    use ConvertsToBaseAmount;

    /**
     * Define the model's default state: an expense category with a planned amount.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'type' => CategoryType::Expense,
            // Suffix keeps names unique per wedding (unique index) without exhausting Faker's unique().
            'name' => Str::title(fake()->words(2, true)).' '.Str::upper(Str::random(4)),
            'planned_amount' => $this->money(5_000_000, 80_000_000, 1_000_000),
        ];
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::Revenue,
            'planned_amount' => null,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::Expense,
        ]);
    }
}
