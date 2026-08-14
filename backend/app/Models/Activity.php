<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'type',
        'base_points',
        'max_attempts',
        'due_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ActivitySubmission::class);
    }
}
