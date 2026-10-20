<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre del unique original: $table->string('idempotency_key')->unique()
     * en create_activity_submissions_table.
     */
    private const ORIGINAL_IDEMPOTENCY_UNIQUE = 'activity_submissions_idempotency_key_unique';

    /**
     * Unique compuesto (user_id, idempotency_key).
     */
    private const USER_SCOPED_IDEMPOTENCY_UNIQUE = 'activity_submissions_user_id_idempotency_key_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->dropUnique(self::ORIGINAL_IDEMPOTENCY_UNIQUE);
            $table->unique(['user_id', 'idempotency_key'], self::USER_SCOPED_IDEMPOTENCY_UNIQUE);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->dropUnique(self::USER_SCOPED_IDEMPOTENCY_UNIQUE);
            $table->unique('idempotency_key', self::ORIGINAL_IDEMPOTENCY_UNIQUE);
        });
    }
};
