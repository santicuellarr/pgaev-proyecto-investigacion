<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(['quiz', 'tarea', 'foro', 'proyecto', 'evaluacion']),
            'base_points' => fake()->randomElement([15, 25, 40, 50, 75, 100]),
            'max_attempts' => fake()->numberBetween(1, 3),
            'due_at' => now()->addDays(fake()->numberBetween(-21, 21)),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
