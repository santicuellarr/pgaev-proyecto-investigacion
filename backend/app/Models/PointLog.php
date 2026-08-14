<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'course_id',
        'source_id',
        'source_type',
        'delta',
        'balance_after',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
