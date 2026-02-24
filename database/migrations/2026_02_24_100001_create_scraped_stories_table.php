<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraped_stories', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();               // truyenfull, tangthuvien, ...
            $table->string('source_id', 150);                    // ID/slug gốc từ nguồn
            $table->string('source_url', 500);                   // URL truyện gốc
            $table->string('title', 255);
            $table->string('author', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url', 500)->nullable();        // URL ảnh bìa gốc (xử lý sau)
            $table->string('status', 50)->default('ongoing');    // ongoing/completed/hiatus
            $table->json('genres')->nullable();                  // Mảng tên thể loại
            $table->integer('total_chapters')->default(0);       // Tổng chương theo nguồn
            $table->integer('scraped_chapters')->default(0);     // Số chương đã crawl được

            // Trạng thái xử lý
            $table->enum('process_status', ['pending', 'processing', 'processed', 'failed'])
                  ->default('pending')->index();                  // Trạng thái xử lý thủ công
            $table->text('process_note')->nullable();            // Ghi chú khi xử lý

            // Sync sang backend
            $table->boolean('is_synced')->default(false)->index(); // Đã sync sang backend chưa
            $table->unsignedBigInteger('synced_story_id')->nullable(); // ID trong backend-doctruyen
            $table->timestamp('synced_at')->nullable();

            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_id']);             // Không crawl trùng
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_stories');
    }
};
