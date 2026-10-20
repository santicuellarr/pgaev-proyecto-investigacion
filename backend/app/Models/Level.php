<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'level_number',
        'name',
        'points_required',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
