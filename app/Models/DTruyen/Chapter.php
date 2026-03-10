<?php

namespace App\Models\DTruyen;

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
 *
 * @property-read Story $story
 */
class Chapter extends Model
{
    protected $table = 'scraped_chapters';

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
        return $this->belongsTo(Story::class, 'story_id');
    }
}
