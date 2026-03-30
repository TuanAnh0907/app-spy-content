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
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->boolean('is_ongoing')->default(true)->after('status')->comment('true = Đang ra, false = Đã hoàn thành/Full');
            $table->unsignedTinyInteger('crawl_retry_count')->default(0)->after('is_ongoing')->comment('Số lần cào lại chương mới (tối đa 4)');
            $table->timestamp('next_crawl_at')->nullable()->after('crawl_retry_count')->comment('Thời điểm tiếp theo cào chương nếu truyện chưa ra hết');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->dropColumn(['is_ongoing', 'crawl_retry_count', 'next_crawl_at']);
        });
    }
};
