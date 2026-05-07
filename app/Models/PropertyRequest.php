<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class PropertyRequest extends Model
{
    use HasFactory, HasNeighbors, Translatable;

    /**
     * The attributes that are translatable.
     *
     * @var array
     */
    protected $translatable = [
        'title',
        'description',
        'requirements'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
        'title',
        'description',
        'property_type',
        'transaction_type',
        'min_budget',
        'max_budget',
        'currency',
        'min_bedrooms',
        'min_bathrooms',
        'min_parking_spaces',
        'min_area',
        'city',
        'state',
        'country',
        'is_active',
        'expires_at',
        'source',
        'source_listing_id',
        'embedding',
        // i18n fields
        'title_i18n',
        'description_i18n',
        'requirements_i18n'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'min_budget' => 'decimal:2',
        'max_budget' => 'decimal:2',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'embedding' => Vector::class,
        // i18n casts
        'title_i18n' => 'array',
        'description_i18n' => 'array',
        'requirements_i18n' => 'array'
    ];

    /**
     * Get the user that owns the property request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope a query to only include expired requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Check if the request is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get formatted budget range.
     */
    public function getBudgetRangeAttribute(): string
    {
        $min = $this->min_budget ? number_format((float) $this->min_budget, 0) : null;
        $max = $this->max_budget ? number_format((float) $this->max_budget, 0) : null;

        if ($min && $max) {
            return "{$this->currency} {$min} - {$max}";
        } elseif ($min) {
            return "{$this->currency} {$min}+";
        } elseif ($max) {
            return "{$this->currency} hasta {$max}";
        }

        return $this->currency ?? '';
    }
}
