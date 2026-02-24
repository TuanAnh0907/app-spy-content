<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraped_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraped_story_id')
                  ->constrained('scraped_stories')
                  ->cascadeOnDelete();
            $table->string('source_url', 500);
            $table->unsignedInteger('chapter_number');
            $table->string('title', 255)->nullable();
            $table->string('content_path', 500)->nullable();
            $table->unsignedInteger('word_count')->default(0);

            // Trạng thái xử lý
            $table->enum('process_status', ['pending', 'processing', 'processed', 'failed'])
                  ->default('pending')->index();
            $table->text('process_note')->nullable();

            // Sync sang backend
            $table->boolean('is_synced')->default(false)->index();
            $table->unsignedBigInteger('synced_chapter_id')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(['scraped_story_id', 'chapter_number']); // Không lưu trùng chương
            $table->index(['scraped_story_id', 'process_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_chapters');
    }
};
