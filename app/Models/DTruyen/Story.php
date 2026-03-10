<?php

namespace App\Models\DTruyen;

use App\Enums\DtruyenStoryType;
use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property string $status
 * @property string|null $title
 * @property string|null $author
 * @property string|null $slug
 * @property int $total_chapters
 * @property string|null $last_error
 * @property DtruyenStoryType|string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection|Chapter[] $chapters
 */
class Story extends Model
{
    protected $table = 'dtruyen_stories';

    protected $fillable = [
        'url',
        'type',
        'status',
        'title',
        'author',
        'slug',
        'normalized_title',
        'normalized_author',
        'total_chapters',
        'last_error',
        'skipped_reason',
    ];

    protected $casts = [
        'type'           => DtruyenStoryType::class,
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
