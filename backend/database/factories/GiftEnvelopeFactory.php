<?php

namespace Database\Factories;

use App\Enums\GuestGroup;
use App\Models\GiftEnvelope;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftEnvelope>
 */
class GiftEnvelopeFactory extends Factory
{
    /**
     * Define the model's default state: a typical Vientiane envelope, 100,000 to 1,000,000 LAK.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'full_name' => fake()->randomElement(['Bounmy Sengdara', 'Noy Phommavong', 'Khamla Inthavong', 'Somphone Keola', 'Vilay Chanthavong']),
            'amount' => fake()->numberBetween(1, 10) * 100_000 .'.00',
            'guest_group' => fake()->randomElement(GuestGroup::cases()),
            'received_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
