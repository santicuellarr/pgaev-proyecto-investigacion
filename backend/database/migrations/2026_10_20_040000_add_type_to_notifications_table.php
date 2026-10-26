<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('message');
        });

        foreach (DB::table('notifications')->whereNull('type')->get() as $notification) {
            $title = (string) $notification->title;
            $type = 'grade';

            if (str_contains($title, 'nivel')) {
                $type = 'level';
            } elseif (str_contains(mb_strtolower($title), 'insignia')) {
                $type = 'badge';
            } elseif (str_contains(mb_strtolower($title), 'canje')) {
                $type = 'redemption';
            }

            DB::table('notifications')->where('id', $notification->id)->update(['type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
