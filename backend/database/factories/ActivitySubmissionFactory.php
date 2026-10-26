<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ActivitySubmission>
 */
class ActivitySubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'attempt_number' => 1,
            'status' => 'submitted',
            'content' => ['text' => fake()->paragraph()],
            'idempotency_key' => Str::uuid()->toString(),
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 20)),
        ];
    }
}
