<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'cost_points',
        'stock',
        'needs_approval',
        'available_from',
        'available_to',
    ];

    protected function casts(): array
    {
        return [
            'needs_approval' => 'boolean',
            'available_from' => 'datetime',
            'available_to' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
