<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ScrapedChapter extends Model
{
    protected $fillable = [
        'scraped_story_id',
        'source_url',
        'chapter_number',
        'title',
        'content_path',
        'word_count',
        'process_status',
        'process_note',
        'is_synced',
        'synced_chapter_id',
        'synced_at',
    ];

    protected $casts = [
        'is_synced' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(ScrapedStory::class, 'scraped_story_id');
    }

    /**
     * Ghi nội dung chương ra file và cập nhật content_path.
     */
    public function writeContent(string $html): void
    {
        $path = "chapters/{$this->scraped_story_id}/{$this->chapter_number}.html";

        Storage::disk(config('filesystems.chapter_disk'))->put($path, $html);

        $this->update(['content_path' => $path]);
    }

    /**
     * Đọc nội dung chương từ file.
     * Trả về chuỗi rỗng nếu file chưa tồn tại.
     */
    public function readContent(): string
    {
        if (!$this->content_path) {
            return '';
        }

        $disk = Storage::disk(config('filesystems.chapter_disk'));

        return $disk->exists($this->content_path)
            ? $disk->get($this->content_path)
            : '';
    }
}
