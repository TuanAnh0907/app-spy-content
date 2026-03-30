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
 * @property bool $is_ongoing
 * @property int $crawl_retry_count
 * @property Carbon|null $next_crawl_at
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
        'cover_image',
        'normalized_title',
        'normalized_author',
        'total_chapters',
        'last_error',
        'skipped_reason',
        'is_ongoing',
        'crawl_retry_count',
        'next_crawl_at',
    ];

    protected $casts = [
        'type'              => DtruyenStoryType::class,
        'status'            => StoryStatus::class,
        'total_chapters'    => 'integer',
        'is_ongoing'        => 'boolean',
        'crawl_retry_count' => 'integer',
        'next_crawl_at'     => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class, 'story_id');
    }
}
