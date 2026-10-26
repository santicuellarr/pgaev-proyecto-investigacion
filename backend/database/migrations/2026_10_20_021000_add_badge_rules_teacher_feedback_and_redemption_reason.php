<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('description');
            $table->string('rule_type')->nullable()->after('trigger_type');
            $table->unsignedInteger('rule_value')->nullable()->after('rule_type');
        });

        $this->makeCourseIdNullable();

        foreach (DB::table('badges')->whereNull('rule_type')->get() as $badge) {
            DB::table('badges')->where('id', $badge->id)->update([
                'rule_type' => $badge->trigger_type,
                'rule_value' => $badge->trigger_type === 'first_submission' ? 1 : null,
            ]);
        }

        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->text('teacher_feedback')->nullable()->after('ai_feedback');
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->dropColumn('teacher_feedback');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['icon', 'rule_type', 'rule_value']);
        });
    }

    private function makeCourseIdNullable(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            Schema::table('badges', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });

            DB::statement('alter table badges alter column course_id drop not null');

            Schema::table('badges', function (Blueprint $table) {
                $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            });

            return;
        }

        Schema::table('badges', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->change();
        });
    }
};
