<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyContact extends Model
{
    protected $fillable = [
        'listing_id',
        'visitor_user_id',
        'owner_user_id',
        'action',
        'notes',
        'seen_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
