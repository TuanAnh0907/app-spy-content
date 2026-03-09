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
            $table->unsignedBigInteger('story_id');
            $table->integer('order_index')->default(0);
            $table->string('chapter_title')->nullable();
            $table->string('chapter_url')->unique();
            $table->string('content_path')->nullable(); // Đường dẫn file lưu nội dung
            $table->string('status')->default('pending'); // pending/processing/completed/failed
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('dtruyen_stories')->onDelete('cascade');
            $table->index(['story_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_chapters');
    }
};
