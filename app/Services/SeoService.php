<?php

namespace App\Services;

use App\Helpers\PropertySlugHelper;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Generate SEO data for property listing
     */
    public function generatePropertySeo(PropertyListing $property, ?string $locale = null): object
    {
        $locale = $locale ?? app()->getLocale();
        
        // Usar el título directo de la propiedad
        $title = $property->title;
        $transactionType = TransactionType::getLabel($property->transaction_type, $locale);
        $canonical = $this->generatePropertyUrl($property, $locale);
        
        return (object) [
            'title' => $title . ' - ' . $transactionType . ' ' . __('properties.in', [], $locale) . ' ' . $property->city,
            'description' => $this->generatePropertyMetaDescription($property, $locale),
            'image' => $property->primaryImage?->image_url ?? ($property->images->first()?->image_url ?? asset('images/default-property.jpg')),
            'type' => 'article',
            'image_w' => 1200,
            'image_h' => 630,
            'locale' => $locale,
            'alternate_locales' => $this->getAlternateLocales($locale),
            'canonical' => $canonical,
            'structured_data' => $this->generatePropertyStructuredData(
                $property,
                $locale,
                $canonical
            ),
        ];
    }

    /**
     * Generate structured data for a property listing.
     *
     * Regional property and transaction values are normalized through their
     * configured English equivalents before selecting Schema.org types.
     */
    private function generatePropertyStructuredData(
        PropertyListing $property,
        string $locale,
        string $canonical
    ): array {
        $countryCode = PropertySlugHelper::getCountryCode((string) $property->country) ?? 'INTL';
        $propertyType = strtolower(
            PropertyType::getValueEn((string) $property->property_type, $countryCode)
                ?? (string) $property->property_type
        );
        $transactionType = strtolower(
            TransactionType::getValueEn((string) $property->transaction_type, $countryCode)
                ?? (string) $property->transaction_type
        );
        $imageUrls = $this->getPropertyImageUrls($property);
        $propertyId = $canonical . '#property';
        $offerId = $canonical . '#offer';

        $propertyData = [
            '@type' => $this->getSchemaPropertyType($propertyType),
            '@id' => $propertyId,
            'name' => $property->title,
            'address' => $this->getPropertyAddress($property, $countryCode),
        ];

        if ($property->latitude !== null && $property->longitude !== null) {
            $propertyData['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $property->latitude,
                'longitude' => (float) $property->longitude,
            ];
        }

        if ($property->bedrooms !== null) {
            $propertyData['numberOfBedrooms'] = (int) $property->bedrooms;
        }

        if ($property->bathrooms !== null) {
            $propertyData['numberOfBathroomsTotal'] = (int) $property->bathrooms;
        }

        if ($property->parking_spaces !== null) {
            $propertyData['numberOfParkingSpaces'] = (int) $property->parking_spaces;
        }

        if ($property->area !== null) {
            $propertyData['floorSize'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $property->area,
                'unitCode' => 'MTK',
            ];
        }

        if ($property->lotsize !== null) {
            $propertyData['lotSize'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $property->lotsize,
                'unitCode' => 'MTK',
            ];
        }

        $offer = [
            '@type' => 'Offer',
            '@id' => $offerId,
            'url' => $canonical,
            'itemOffered' => ['@id' => $propertyId],
            'availability' => 'https://schema.org/InStock',
        ];

        if ($property->price !== null) {
            $offer['price'] = (float) $property->price;
        }

        if (filled($property->currency)) {
            $offer['priceCurrency'] = strtoupper((string) $property->currency);
        }

        $graph = [
            [
                '@type' => 'RealEstateListing',
                '@id' => $canonical . '#listing',
                'url' => $canonical,
                'name' => $property->title,
                'description' => $this->generatePropertyMetaDescription($property, $locale),
                'inLanguage' => $locale,
                'image' => $imageUrls,
                'about' => ['@id' => $propertyId],
                'offers' => ['@id' => $offerId],
            ],
            $propertyData,
            $offer,
            $this->generateBreadcrumbStructuredData($property, $locale, $canonical),
        ];

        $businessFunction = $this->getBusinessFunction($transactionType);

        if ($businessFunction !== null) {
            $graph[2]['businessFunction'] = $businessFunction;
        }

        $seller = $this->generateSellerStructuredData($property, $locale);

        if ($seller !== null) {
            $graph[2]['seller'] = $seller;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @return list<string>
     */
    private function getPropertyImageUrls(PropertyListing $property): array
    {
        $images = $property->images
            ->pluck('image_url')
            ->filter()
            ->map(fn (string $imageUrl): string => $this->toAbsoluteUrl($imageUrl))
            ->unique()
            ->values()
            ->all();

        if (empty($images)) {
            $images[] = asset('images/default-property.jpg');
        }

        return $images;
    }

    private function toAbsoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url('/' . ltrim($url, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getPropertyAddress(PropertyListing $property, string $countryCode): array
    {
        $address = [
            '@type' => 'PostalAddress',
            'addressLocality' => $property->city,
            'addressRegion' => $property->state,
            'addressCountry' => $countryCode === 'INTL' ? $property->country : $countryCode,
        ];

        if (filled($property->address)) {
            $address['streetAddress'] = $property->address;
        }

        if (filled($property->postal_code)) {
            $address['postalCode'] = $property->postal_code;
        }

        return array_filter($address, fn (mixed $value): bool => filled($value));
    }

    private function getSchemaPropertyType(string $propertyType): string
    {
        return match ($propertyType) {
            'apartment' => 'Apartment',
            'house' => 'SingleFamilyResidence',
            'office' => 'OfficeBuilding',
            'parking' => 'ParkingFacility',
            default => 'Place',
        };
    }

    private function getBusinessFunction(string $transactionType): ?string
    {
        return match ($transactionType) {
            'sale' => 'https://schema.org/Sell',
            'rent', 'temporary_rent' => 'https://schema.org/LeaseOut',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSellerStructuredData(
        PropertyListing $property,
        string $locale
    ): ?array {
        if (!$property->relationLoaded('user') || !$property->user) {
            return null;
        }

        $seller = [
            '@type' => 'Person',
            'name' => $property->user->name,
        ];

        if (filled($property->user->username)) {
            $routeName = $locale === 'en' ? 'user.profile.en' : 'user.profile.es';
            $seller['url'] = route($routeName, [
                'locale' => $locale,
                'username' => $property->user->username,
            ]);
        }

        if (filled($property->user->agency)) {
            $seller['worksFor'] = [
                '@type' => 'Organization',
                'name' => $property->user->agency,
            ];
        }

        return $seller;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBreadcrumbStructuredData(
        PropertyListing $property,
        string $locale,
        string $canonical
    ): array {
        $countryUrl = url("/{$locale}/" . Str::slug($property->country));
        $cityUrl = url("/{$locale}/" . Str::slug($property->country) . '/' . Str::slug($property->city));

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $canonical . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => __('messages.home', [], $locale),
                    'item' => url("/{$locale}"),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $property->country,
                    'item' => $countryUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $property->city,
                    'item' => $cityUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $property->title,
                    'item' => $canonical,
                ],
            ],
        ];
    }

    /**
     * Generate meta description for property listing
     */
    public function generatePropertyMetaDescription(PropertyListing $property, string $locale = 'es'): string
    {
        $parts = [];
        
        // Property type and transaction
        $propertyType    = PropertyType::getLabel($property->property_type, $locale);
        $transactionType = TransactionType::getLabel($property->transaction_type, $locale);
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
            $tags[] = [
                'rel' => 'alternate',
                'hreflang' => $locale,
                'href' => $this->generatePropertyUrl($property, $locale),
            ];
        }
        
        // Add x-default
        $tags[] = [
            'rel' => 'alternate',
            'hreflang' => 'x-default',
            'href' => $this->generatePropertyUrl($property, config('app.fallback_locale')),
        ];
        
        return $tags;
    }

    /**
     * Generate slug for property in specific locale
     */
    public function generatePropertySlug(PropertyListing $property, ?string $locale = null): string
    {
        // Usar el título directo de la propiedad
        return Str::slug($property->title);
    }

    /**
     * Generate property URL with SEO structure
     * Format: /{locale}/{country}/{city}/propiedad/{id}-{slug}
     */
    public function generatePropertyUrl(PropertyListing $property, string $locale): string
    {
        $countrySlug = Str::slug($property->country);
        $citySlug = Str::slug($property->city);
        $titleSlug = Str::slug($property->title);
        
        return url("/{$locale}/{$countrySlug}/{$citySlug}/propiedad/{$property->id}-{$titleSlug}");
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
