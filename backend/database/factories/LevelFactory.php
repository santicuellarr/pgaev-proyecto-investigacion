<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 6);

        return [
            'course_id' => Course::factory(),
            'level_number' => $number,
            'name' => fake()->randomElement(['Novato', 'Aprendiz', 'Practicante', 'Avanzado', 'Experto', 'Maestro']),
            'points_required' => ($number - 1) * 50,
        ];
    }
}
