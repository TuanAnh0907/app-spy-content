<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model
{
    protected $fillable = [
        'source',
        'type',
        'url',
        'status',
        'message',
        'http_code',
        'duration_ms',
    ];
}
