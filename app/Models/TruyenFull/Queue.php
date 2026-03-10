<?php

namespace App\Models\TruyenFull;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property string $status  pending|processing|completed|failed
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Queue extends Model
{
    protected $table = 'tf_queues';

    protected $fillable = [
        'url',
        'status',
        'last_error',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
