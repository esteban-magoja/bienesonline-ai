<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileMember extends Model
{
    /** @use HasFactory<\Database\Factories\UserProfileMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'role',
        'photo_path',
        'bio_i18n',
        'specialties',
        'areas',
        'phone',
        'email',
        'show_phone',
        'show_email',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'bio_i18n' => 'array',
            'specialties' => 'array',
            'areas' => 'array',
            'show_phone' => 'boolean',
            'show_email' => 'boolean',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localizedBio(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $translations = $this->bio_i18n ?? [];

        return $translations[$locale] ?? $translations['es'] ?? null;
    }
}
