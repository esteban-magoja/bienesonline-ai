<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wave\Plan;

class BillingPlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'provider',
        'cycle',
        'external_id',
        'amount',
        'currency',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
