<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'description',
        'image_url',
        'trigger_type',
        'trigger_config',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}
