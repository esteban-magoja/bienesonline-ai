<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileSetting extends Model
{
    /** @use HasFactory<\Database\Factories\UserProfileSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'cover_image_path',
        'website_url',
        'social_links',
        'office_hours',
        'show_email',
        'show_phone',
        'show_address',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'office_hours' => 'array',
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_address' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
