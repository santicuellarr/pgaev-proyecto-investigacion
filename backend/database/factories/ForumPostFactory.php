<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory()->state(['type' => 'foro']),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
        ];
    }
}
