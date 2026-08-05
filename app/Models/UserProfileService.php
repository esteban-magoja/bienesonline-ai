<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileService extends Model
{
    /** @use HasFactory<\Database\Factories\UserProfileServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name_i18n',
        'description_i18n',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_i18n' => 'array',
            'description_i18n' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the Blade icon components available to profile owners.
     *
     * @return array<int, string>
     */
    public static function allowedIconComponents(): array
    {
        return [
            'phosphor-buildings',
            'phosphor-house',
            'phosphor-handshake',
            'phosphor-magnifying-glass',
            'phosphor-users-three',
            'phosphor-note',
        ];
    }

    public function iconComponent(): ?string
    {
        return in_array($this->icon, self::allowedIconComponents(), true)
            ? $this->icon
            : null;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $translations = $this->name_i18n ?? [];

        return $translations[$locale] ?? $translations['es'] ?? '';
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $translations = $this->description_i18n ?? [];

        return $translations[$locale] ?? $translations['es'] ?? null;
    }
}
