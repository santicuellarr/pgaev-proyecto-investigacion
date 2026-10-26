<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_path_composite_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('point_logs', 'point_logs_user_course_created_index')
            || Schema::hasIndex('point_logs', ['user_id', 'course_id', 'created_at']));

        $this->assertTrue(Schema::hasIndex('activity_submissions', 'activity_submissions_activity_user_index')
            || Schema::hasIndex('activity_submissions', ['activity_id', 'user_id']));

        $this->assertTrue(Schema::hasIndex('user_course_stats', 'user_course_stats_course_points_index')
            || Schema::hasIndex('user_course_stats', ['course_id', 'total_points']));

        $this->assertTrue(Schema::hasIndex('redemptions', 'redemptions_reward_status_index')
            || Schema::hasIndex('redemptions', ['reward_id', 'status']));
    }
}
