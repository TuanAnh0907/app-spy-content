<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_id
 * @property int $order_index
 * @property string|null $chapter_title
 * @property string $chapter_url
 * @property string|null $content_path
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DtruyenStory $story
 */
class ScrapedChapter extends Model
{
    // use HasFactory; // Factory chưa được định nghĩa, tạm thời bỏ để tránh warning

    protected $fillable = [
        'story_id',
        'order_index',
        'chapter_title',
        'chapter_url',
        'content_path',
        'status',
        'last_error',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'last_error'  => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DtruyenStory::class, 'story_id');
    }
}
