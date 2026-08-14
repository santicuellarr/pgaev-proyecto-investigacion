<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->smallInteger('attempt_number');
            $table->string('status');
            $table->jsonb('content')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_submissions');
    }
};
