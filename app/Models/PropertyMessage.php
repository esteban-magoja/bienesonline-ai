<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMessage extends Model
{
    protected $fillable = [
        'property_listing_id',
        'user_id',
        'name',
        'email',
        'phone',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Get the property listing associated with this message.
     */
    public function propertyListing()
    {
        return $this->belongsTo(PropertyListing::class);
    }

    /**
     * Get the user who sent the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
