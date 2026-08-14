<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCourseStat extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'total_points',
        'current_level',
        'streak_days',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
