<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Enums\DtruyenStoryType;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property-read Collection|ScrapedChapter[] $scrapedChapters
 */
class DtruyenStory extends Model
{
    protected $table = 'dtruyen_stories';

    protected $fillable = [
        'url',
        'type',
        'status',
        'title',
        'author',
        'slug',
        'total_chapters',
        'last_error',
    ];

    protected $casts = [
        'type'           => DtruyenStoryType::class,
        'total_chapters' => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function scrapedChapters(): HasMany
    {
        return $this->hasMany(ScrapedChapter::class, 'story_id');
    }
}
