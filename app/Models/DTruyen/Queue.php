<?php

namespace App\Models\DTruyen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Queue extends Model
{
    protected $table = 'dtruyen_queues';

    protected $fillable = [
        'url',
        'status',
        'last_error',
    ];

    protected $casts = [
        'last_error' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
