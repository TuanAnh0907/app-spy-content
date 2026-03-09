<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DtruyenQueue extends Model
{
    use HasFactory;

    protected $table = 'dtruyen_queues';

    protected $fillable = [
        'url',
        'status',
        'last_error'
    ];
}
