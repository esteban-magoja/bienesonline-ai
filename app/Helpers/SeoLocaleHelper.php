<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SeoLocaleHelper
{
    public static function getLanguageTag(string $locale, ?string $countryCode = null): string
    {
        $language = Str::lower($locale);
        $countryCode = Str::upper(trim((string) $countryCode));

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return $language;
        }

        return "{$language}-{$countryCode}";
    }

    public static function getOpenGraphLocale(string $locale, ?string $countryCode = null): string
    {
        $languageTag = self::getLanguageTag($locale, $countryCode);

        if (str_contains($languageTag, '-')) {
            return str_replace('-', '_', $languageTag);
        }

        return match ($languageTag) {
            'es' => 'es_ES',
            'en' => 'en_US',
            default => $languageTag,
        };
    }
}
