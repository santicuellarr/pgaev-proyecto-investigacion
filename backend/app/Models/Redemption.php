<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    protected $fillable = [
        'user_id',
        'reward_id',
        'idempotency_key',
        'status',
        'redeemed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
