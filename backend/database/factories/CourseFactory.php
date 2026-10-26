<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->paragraph(),
            'invite_code' => strtoupper(fake()->unique()->bothify('??##??')),
            'is_active' => true,
            'status' => Course::STATUS_PUBLISHED,
            'color' => fake()->randomElement(['#4f46e5', '#0ea5e9', '#f59e0b', '#10b981', '#ec4899']),
            'starts_at' => now()->subWeeks(8),
            'ends_at' => now()->addMonths(2),
        ];
    }
}
