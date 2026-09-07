<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wave\Plan;

class BillingCheckoutAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'provider',
        'cycle',
        'idempotency_key',
        'external_id',
        'status',
        'metadata',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
