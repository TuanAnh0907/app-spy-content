<?php

namespace App\Models;

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
class DtruyenQueue extends Model
{
    protected $table = 'dtruyen_queues';

    protected $fillable = [
        'url',
        'status',
        'last_error'
    ];

    protected $casts = [
        'last_error' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
