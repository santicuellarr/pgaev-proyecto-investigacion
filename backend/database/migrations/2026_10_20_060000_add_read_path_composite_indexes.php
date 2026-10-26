<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{columns: list<string>, name: string}>
     */
    private array $indexes = [
        'point_logs' => [
            'columns' => ['user_id', 'course_id', 'created_at'],
            'name' => 'point_logs_user_course_created_index',
        ],
        'activity_submissions' => [
            'columns' => ['activity_id', 'user_id'],
            'name' => 'activity_submissions_activity_user_index',
        ],
        'user_course_stats' => [
            'columns' => ['course_id', 'total_points'],
            'name' => 'user_course_stats_course_points_index',
        ],
        'redemptions' => [
            'columns' => ['reward_id', 'status'],
            'name' => 'redemptions_reward_status_index',
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $index) {
            if ($this->indexExists($table, $index['columns'], $index['name'])) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->index($index['columns'], $index['name']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $index) {
            if (! $this->indexExists($table, $index['columns'], $index['name'])) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index['name']);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexExists(string $table, array $columns, string $name): bool
    {
        return Schema::hasIndex($table, $name)
            || Schema::hasIndex($table, $columns);
    }
};
