<?php

namespace Database\Factories;

use App\Models\Badge;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => '🏅',
            'trigger_type' => Badge::RULE_MANUAL,
            'trigger_config' => ['event' => 'manual'],
            'rule_type' => Badge::RULE_MANUAL,
            'rule_value' => null,
        ];
    }
}
