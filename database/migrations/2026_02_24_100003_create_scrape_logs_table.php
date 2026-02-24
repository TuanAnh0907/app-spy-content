<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->enum('type', ['story', 'chapter']);
            $table->string('url', 500);
            $table->enum('status', ['success', 'failed', 'skipped'])->index();
            $table->text('message')->nullable();          // Lỗi hoặc ghi chú
            $table->unsignedInteger('http_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable(); // Thời gian crawl
            $table->timestamps();

            $table->index(['source', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_logs');
    }
};
