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
        $tables = [
            'dtruyen_stories',
            'dtruyen_chapters',
            'tf_stories',
            'tf_chapters'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                // Thêm timestamp để biết đã đồng bộ qua backend hay chưa (null = chưa gửi)
                $t->timestamp('synced_at')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'dtruyen_stories',
            'dtruyen_chapters',
            'tf_stories',
            'tf_chapters'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('synced_at');
            });
        }
    }
};
