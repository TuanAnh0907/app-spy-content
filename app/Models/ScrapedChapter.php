<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapedChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'order_index',
        'chapter_title',
        'chapter_url',
        'content_path',
        'status',
        'last_error',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DtruyenStory::class, 'story_id');
    }
}
