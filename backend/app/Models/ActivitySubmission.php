<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySubmission extends Model
{
    protected $fillable = [
        'activity_id',
        'user_id',
        'attempt_number',
        'status',
        'content',
        'score',
        'idempotency_key',
        'submitted_at',
        'graded_at',
        'ai_feedback',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
