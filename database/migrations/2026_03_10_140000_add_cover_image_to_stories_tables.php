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
            $table->string('cover_image')->nullable()->after('slug')->comment('Đường dẫn file ảnh banner/cover đã download');
        });

        Schema::table('tf_stories', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('slug')->comment('Đường dẫn file ảnh banner/cover đã download');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });

        Schema::table('tf_stories', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};

