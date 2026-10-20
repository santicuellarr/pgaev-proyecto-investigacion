<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => fake()->words(3, true),
            'cost_points' => fake()->randomElement([40, 60, 80, 120]),
            'stock' => fake()->numberBetween(5, 20),
            'needs_approval' => fake()->boolean(),
            'available_from' => now()->subWeeks(8),
            'available_to' => now()->addMonths(2),
        ];
    }
}
