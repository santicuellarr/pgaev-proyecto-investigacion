<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => 'Encuentro: '.fake()->sentence(3),
            'starts_at' => now()->subDays(fake()->numberBetween(-10, 40))->setTime(18, 0),
            'duration_min' => fake()->randomElement([60, 90, 120]),
            'meeting_url' => 'https://meet.example.edu/'.fake()->slug(2),
        ];
    }
}
