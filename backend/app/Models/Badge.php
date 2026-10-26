<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasFactory;

    public const RULE_FIRST_SUBMISSION = 'first_submission';

    public const RULE_SUBMISSIONS_COUNT = 'submissions_count';

    public const RULE_POINTS_TOTAL = 'points_total';

    public const RULE_LEVEL_REACHED = 'level_reached';

    public const RULE_MANUAL = 'manual';

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'icon',
        'image_url',
        'trigger_type',
        'trigger_config',
        'rule_type',
        'rule_value',
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
