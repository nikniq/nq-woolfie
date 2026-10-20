<?php

namespace Database\Factories;

use App\Models\SavedGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedGame>
 */
class SavedGameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'state' => [
                'v' => 1,
                'levelIndex' => fake()->numberBetween(0, 4),
                'score' => fake()->numberBetween(0, 3000),
                'lives' => fake()->numberBetween(1, 3),
                'savedAt' => now()->getTimestampMs(),
            ],
        ];
    }
}
