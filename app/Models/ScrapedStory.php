<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapedStory extends Model
{
    protected $fillable = [
        'source',
        'source_id',
        'source_url',
        'title',
        'author',
        'description',
        'cover_url',
        'status',
        'genres',
        'total_chapters',
        'scraped_chapters',
        'process_status',
        'process_note',
        'is_synced',
        'synced_story_id',
        'synced_at',
        'last_scraped_at',
    ];

    protected $casts = [
        'genres'          => 'array',
        'is_synced'       => 'boolean',
        'last_scraped_at' => 'datetime',
        'synced_at'       => 'datetime',
    ];

    public function chapters(): HasMany
    {
        return $this->hasMany(ScrapedChapter::class)->orderBy('chapter_number');
    }

    /**
     * Số chương chưa được sync lên backend
     */
    public function unsyncedChapters(): HasMany
    {
        return $this->hasMany(ScrapedChapter::class)->where('is_synced', false);
    }

    public function isPending(): bool
    {
        return $this->process_status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->process_status === 'processed';
    }

    public function isSynced(): bool
    {
        return $this->is_synced;
    }
}
