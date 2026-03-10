<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tf_stories', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique()->comment('URL trang chủ truyện trên TruyenFull');
            $table->string('title')->nullable()->comment('Tên truyện');
            $table->string('author')->nullable()->comment('Tác giả');
            $table->string('slug')->nullable()->comment('Slug dùng để tạo thư mục lưu nội dung');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_chapters')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_stories');
    }
};
