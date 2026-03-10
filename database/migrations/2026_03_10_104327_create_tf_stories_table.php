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
            $table->string('normalized_title')->nullable()->index()->comment('Title đã normalize (lowercase, bỏ dấu) để dedup');
            $table->string('normalized_author')->nullable()->index()->comment('Author đã normalize để dedup');
            $table->unsignedTinyInteger('status')->default(0)->comment('0=pending,1=processing,2=completed,3=failed,4=skipped');
            $table->unsignedInteger('total_chapters')->default(0);
            $table->text('last_error')->nullable();
            $table->string('skipped_reason')->nullable()->comment('Lý do bị bỏ qua, vd: "Duplicate of dtruyen_stories#15"');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_stories');
    }
};
