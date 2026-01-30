<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class PropertyListing extends Model
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
        'features',
        'location_details'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'property_type',
        'transaction_type',
        'price',
        'bedrooms',
        'bathrooms',
        'parking_spaces',
        'area',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'is_featured',
        'is_active',
        'embedding',
        'conditions',
        'currency',
        'lotsize',
        // i18n fields
        'title_i18n',
        'description_i18n',
        'features_i18n',
        'location_details_i18n'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'embedding' => Vector::class,
        // i18n casts
        'title_i18n' => 'array',
        'description_i18n' => 'array',
        'features_i18n' => 'array',
        'location_details_i18n' => 'array'
    ];

    /**
     * Get the user that owns the property listing.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the images for the property listing.
     */
    public function images()
    {
        return $this->hasMany(PropertyImage::class);
    }

    /**
     * Get the primary image for the property listing.
     */
    public function primaryImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_primary', true);
    }

    /**
     * Get the messages for the property listing.
     */
    public function messages()
    {
        return $this->hasMany(PropertyMessage::class);
    }

    /**
     * Scope a query to only include active listings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured listings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
