<?php

namespace App\Models\TruyenFull;

use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property string|null $title
 * @property string|null $author
 * @property string|null $slug
 * @property string $status  pending|processing|completed|failed
 * @property int $total_chapters
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Chapter[] $chapters
 */
class Story extends Model
{
    protected $table = 'tf_stories';

    protected $fillable = [
        'url',
        'title',
        'author',
        'slug',
        'normalized_title',
        'normalized_author',
        'status',
        'total_chapters',
        'last_error',
        'skipped_reason',
    ];

    protected $casts = [
        'status'         => StoryStatus::class,
        'total_chapters' => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class, 'story_id');
    }
}
