<?php

namespace App\Models\TruyenFull;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_id
 * @property string $chapter_url
 * @property string|null $chapter_title
 * @property int $order_index
 * @property string|null $content_path
 * @property string $status  pending|processing|completed|failed
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Story $story
 */
class Chapter extends Model
{
    protected $table = 'tf_chapters';

    protected $fillable = [
        'story_id',
        'chapter_url',
        'chapter_title',
        'order_index',
        'content_path',
        'status',
        'last_error',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'story_id');
    }
}
