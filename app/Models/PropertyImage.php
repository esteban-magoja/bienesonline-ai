<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'property_listing_id',
        'image_path',
        'image_url',
        'alt_text',
        'is_primary',
        'sort_order'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_primary' => 'boolean'
    ];

    /**
     * Get the property listing that owns the image.
     */
    public function propertyListing()
    {
        return $this->belongsTo(PropertyListing::class);
    }
}
