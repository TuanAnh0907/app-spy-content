<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('url'); // pending/processing/completed/failed
            $table->string('title')->nullable()->after('status');
            $table->string('author')->nullable()->after('title');
            $table->string('slug')->nullable()->after('author');
            $table->integer('total_chapters')->default(0)->after('slug');
            $table->text('last_error')->nullable()->after('total_chapters');
        });
    }

    public function down(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->dropColumn(['status', 'title', 'author', 'slug', 'total_chapters', 'last_error']);
        });
    }
};
