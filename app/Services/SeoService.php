<?php

namespace App\Services;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Generate SEO data for property listing
     */
    public function generatePropertySeo(PropertyListing $property, ?string $locale = null): object
    {
        $locale = $locale ?? app()->getLocale();
        
        $title = $property->getTranslation('title', $locale);
        $transactionType = __('properties.transaction_types.' . $property->transaction_type, [], $locale);
        
        return (object) [
            'title' => $title . ' - ' . $transactionType . ' ' . __('properties.in', [], $locale) . ' ' . $property->city,
            'description' => $this->generatePropertyMetaDescription($property, $locale),
            'image' => $property->primaryImage?->image_url ?? ($property->images->first()?->image_url ?? asset('images/default-property.jpg')),
            'type' => 'article',
            'image_w' => 1200,
            'image_h' => 630,
            'locale' => $locale,
            'alternate_locales' => $this->getAlternateLocales($locale),
            'canonical' => route_localized('property.show', ['id' => $property->id, 'slug' => Str::slug($title)], $locale),
        ];
    }

    /**
     * Generate meta description for property listing
     */
    public function generatePropertyMetaDescription(PropertyListing $property, string $locale = 'es'): string
    {
        $parts = [];
        
        // Property type and transaction
        $propertyType = __('properties.types.' . $property->property_type, [], $locale);
        $transactionType = __('properties.transaction_types.' . $property->transaction_type, [], $locale);
        $parts[] = ucfirst($propertyType) . ' ' . __('properties.in', [], $locale) . ' ' . $transactionType;
        
        // Location
        $parts[] = $property->city . ', ' . $property->state . ', ' . $property->country;
        
        // Price
        $parts[] = $property->currency . ' ' . number_format($property->price);
        
        // Main features
        $features = [];
        if ($property->bedrooms) {
            $features[] = $property->bedrooms . ' ' . __('properties.features.bedrooms_short', [], $locale);
        }
        if ($property->bathrooms) {
            $features[] = $property->bathrooms . ' ' . __('properties.features.bathrooms_short', [], $locale);
        }
        if ($property->area) {
            $features[] = number_format($property->area) . 'm²';
        }
        if ($property->parking_spaces) {
            $parkingLabel = __('properties.features.parking_short', [], $locale);
            $features[] = $property->parking_spaces . ' ' . $parkingLabel;
        }
        
        if (!empty($features)) {
            $parts[] = implode(', ', $features);
        }
        
        // Join all parts
        $description = implode(' • ', $parts);
        
        // Limit to 160 characters for SEO
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }
        
        return $description;
    }

    /**
     * Generate hreflang tags for property
     */
    public function generateHreflangTags(PropertyListing $property): array
    {
        $tags = [];
        $supported = config('locales.supported', ['es', 'en']);
        
        foreach ($supported as $locale) {
            $title = $property->getTranslation('title', $locale);
            $slug = Str::slug($title);
            $url = route_localized('property.show', ['id' => $property->id, 'slug' => $slug], $locale);
            
            $tags[] = [
                'rel' => 'alternate',
                'hreflang' => $locale,
                'href' => $url,
            ];
        }
        
        // Add x-default
        $defaultTitle = $property->getTranslation('title', config('app.fallback_locale'));
        $defaultSlug = Str::slug($defaultTitle);
        $tags[] = [
            'rel' => 'alternate',
            'hreflang' => 'x-default',
            'href' => route_localized('property.show', ['id' => $property->id, 'slug' => $defaultSlug], config('app.fallback_locale')),
        ];
        
        return $tags;
    }

    /**
     * Generate slug for property in specific locale
     */
    public function generatePropertySlug(PropertyListing $property, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $title = $property->getTranslation('title', $locale);
        return Str::slug($title);
    }

    /**
     * Get alternate locales (not including current)
     */
    private function getAlternateLocales(string $currentLocale): array
    {
        $supported = config('locales.supported', ['es', 'en']);
        return array_filter(
            $supported,
            fn($locale) => $locale !== $currentLocale
        );
    }

    /**
     * Generate Open Graph locale tags
     */
    public function generateOgLocaleTags(string $locale): array
    {
        $localeMap = [
            'es' => 'es_ES',
            'en' => 'en_US',
        ];
        
        $ogLocale = $localeMap[$locale] ?? 'es_ES';
        $supported = config('locales.supported', ['es', 'en']);
        
        $alternates = array_filter(
            array_map(fn($l) => $localeMap[$l] ?? null, $supported),
            fn($l) => $l !== $ogLocale
        );
        
        return [
            'locale' => $ogLocale,
            'alternate_locales' => array_values($alternates),
        ];
    }
}
