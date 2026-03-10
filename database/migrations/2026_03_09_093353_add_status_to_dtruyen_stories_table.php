<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(0)->after('url')->comment('0=pending,1=processing,2=completed,3=failed,4=skipped');
            $table->string('title')->nullable()->after('status');
            $table->string('author')->nullable()->after('title');
            $table->string('slug')->nullable()->after('author');
            $table->string('normalized_title')->nullable()->index()->after('slug')->comment('Title đã normalize (lowercase, bỏ dấu) để dedup');
            $table->string('normalized_author')->nullable()->index()->after('normalized_title')->comment('Author đã normalize để dedup');
            $table->integer('total_chapters')->default(0)->after('normalized_author');
            $table->text('last_error')->nullable()->after('total_chapters');
            $table->string('skipped_reason')->nullable()->after('last_error')->comment('Lý do bị bỏ qua, vd: "Duplicate of tf_stories#42"');
        });
    }

    public function down(): void
    {
        Schema::table('dtruyen_stories', function (Blueprint $table) {
            $table->dropColumn(['status', 'title', 'author', 'slug', 'normalized_title', 'normalized_author', 'total_chapters', 'last_error', 'skipped_reason']);
        });
    }
};
