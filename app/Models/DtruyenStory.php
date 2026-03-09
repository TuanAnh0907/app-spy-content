<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\DtruyenStoryType;

class DtruyenStory extends Model
{
    use HasFactory;

    protected $table = 'dtruyen_stories';

    protected $fillable = [
        'url',
        'type'
    ];

    protected $casts = [
        'type' => DtruyenStoryType::class,
    ];
}
