<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tf_chapters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id')->comment('FK → tf_stories.id');
            $table->string('chapter_url')->unique()->comment('URL trang chương TruyenFull');
            $table->string('chapter_title')->nullable();
            $table->unsignedInteger('order_index')->default(0)->comment('Số thứ tự chương');
            $table->string('content_path')->nullable()->comment('Đường dẫn tương đối file txt trên disk');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('tf_stories')->cascadeOnDelete();
            $table->index(['story_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_chapters');
    }
};
