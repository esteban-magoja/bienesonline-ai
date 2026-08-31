<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Helpers\PropertySlugHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;

class PropertyListingController extends Controller
{
    /**
     * Muestra listados de propiedades con URLs amigables SEO
     *
     * Estructura de URL:
     * /{locale}/{país}/{operación?}/{tipo?}/{estado?}/{ciudad?}
     *
     * Los slugs de operación y tipo se corresponden directamente con el campo
     * `value` configurado para el país en las tablas transaction_types / property_types.
     * Los slugs de estado y ciudad se validan contra nnjeim/world (sin acentos/mayúsculas).
     */
    public function index(Request $request, string $locale, string $country, ?string $params = null)
    {
        App::setLocale($locale);

        // Validar que el país exista en la BD de anuncios
        $countryName = PropertySlugHelper::validateCountry($country);
        if (!$countryName) {
            abort(404, "País no encontrado: {$country}");
        }

        // Obtener ISO2 del país para consultar tipos configurados
        $countryCode = PropertySlugHelper::getCountryCode($countryName) ?? 'INTL';

        // Parsear parámetros opcionales en cascada
        $paramsArray = $params ? explode('/', trim($params, '/')) : [];
        [$transactionType, $propertyType, $state, $city] = $this->parseUrlParams(
            $paramsArray, $countryName, $countryCode
        );

        // Construir query base
        $query = PropertyListing::where('is_active', true)
            ->where('country', $countryName);

        // Filtrar por tipo de operación (match directo sobre el value del país)
        if ($transactionType) {
            $query->whereRaw('LOWER(transaction_type) = LOWER(?)', [$transactionType->value]);
        }

        // Filtrar por tipo de propiedad (match directo sobre el value del país)
        if ($propertyType) {
            $query->whereRaw('LOWER(property_type) = LOWER(?)', [$propertyType->value]);
        }

        // Filtrar por estado/provincia (acepta diferencias de acentos y mayúsculas)
        if ($state) {
            $query->whereRaw('lower(unaccent(state)) = lower(unaccent(?))', [$state->name]);
        }

        // Filtrar por ciudad (acepta diferencias de acentos y mayúsculas)
        if ($city) {
            $query->whereRaw('lower(unaccent(city)) = lower(unaccent(?))', [$city->name]);
        }

        // Aplicar filtros de sidebar (query params)
        $query = $this->applySidebarFilters($query, $request);

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request);

        // Paginación
        $properties = $query->with(['user', 'primaryImage', 'firstImage'])->paginate(20)->withQueryString();

        // Generar breadcrumbs con tipos del país
        $breadcrumbs = PropertySlugHelper::generateBreadcrumbs(
            $locale,
            $countryName,
            $countryCode,
            $transactionType,
            $propertyType,
            $state,
            $city
        );

        // Generar metadata SEO
        $seo = $this->generateSeoMetadata(
            $countryName, $countryCode, $transactionType, $propertyType, $state, $city,
            $properties->total(), $locale
        );

        // Opciones de filtros para el sidebar
        $filterOptions = $this->getFilterOptions($countryName, $state, $city);

        // Navegación contextual para continuar explorando el listado.
        $countryHubSections = $this->getListingNavigationSections(
            $countryName,
            $countryCode,
            $locale,
            $transactionType,
            $propertyType,
            $state,
            $city
        );

        $countryHubContent = $this->getListingHubContent(
            $countryName,
            $locale,
            $transactionType,
            $propertyType,
            $state,
            $city
        );

        // Array de filtros para la vista (compatibilidad)
        $filters = [
            'country'          => $countryName,
            'transaction_type' => $transactionType?->value,
            'property_type'    => $propertyType?->value,
            'state'            => $state?->name,
            'city'             => $city?->name,
        ];

        return view('property-listing', compact(
            'properties',
            'filters',
            'breadcrumbs',
            'seo',
            'filterOptions',
            'countryHubSections',
            'countryHubContent',
            'locale'
        ));
    }

    /**
     * Genera el contenido introductorio correspondiente al nivel actual del listado.
     *
     * La página de país funciona como hub general; las páginas filtradas describen
     * la combinación de operación, tipo y ubicación que el usuario está viendo.
     */
    private function getListingHubContent(
        string $countryName,
        string $locale,
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        ?State $state,
        ?City $city
    ): array {
        if (!$transactionType && !$propertyType && !$state && !$city) {
            return [
                'title' => __('properties.country_hub.title', ['country' => $countryName], $locale),
                'description' => __('properties.country_hub.description', ['country' => $countryName], $locale),
            ];
        }

        $propertyLabel = $propertyType
            ? $this->getPropertyTypePluralLabel($propertyType, $locale)
            : __('properties.country_hub.properties', [], $locale);
        $transactionLabel = $transactionType
            ? Str::lower(TransactionType::getLabel($transactionType->value, $locale))
            : null;
        $location = $this->getListingLocationLabel($countryName, $state, $city);

        if ($propertyType && $transactionType) {
            return [
                'title' => __('properties.country_hub.context.type_and_transaction_title', [
                    'property_type' => $propertyLabel,
                    'transaction_type' => $transactionLabel,
                    'location' => $location,
                ], $locale),
                'description' => __('properties.country_hub.context.type_and_transaction_description', [
                    'property_type' => Str::lower($propertyLabel),
                    'transaction_type' => $transactionLabel,
                    'location' => $location,
                ], $locale),
            ];
        }

        if ($propertyType) {
            return [
                'title' => __('properties.country_hub.context.type_title', [
                    'property_type' => $propertyLabel,
                    'location' => $location,
                ], $locale),
                'description' => __('properties.country_hub.context.type_description', [
                    'property_type' => Str::lower($propertyLabel),
                    'location' => $location,
                ], $locale),
            ];
        }

        if ($transactionType) {
            return [
                'title' => __('properties.country_hub.context.transaction_title', [
                    'properties' => $propertyLabel,
                    'transaction_type' => $transactionLabel,
                    'location' => $location,
                ], $locale),
                'description' => __('properties.country_hub.context.transaction_description', [
                    'transaction_type' => $transactionLabel,
                    'location' => $location,
                ], $locale),
            ];
        }

        return [
            'title' => __('properties.country_hub.context.location_title', [
                'properties' => $propertyLabel,
                'location' => $location,
            ], $locale),
            'description' => __('properties.country_hub.context.location_description', [
                'location' => $location,
            ], $locale),
        ];
    }

    private function getPropertyTypePluralLabel(PropertyType $propertyType, string $locale): string
    {
        if ($locale !== 'en') {
            return $propertyType->label_plural ?: $propertyType->label;
        }

        return Str::plural(PropertyType::getLabel($propertyType->value, $locale));
    }

    private function getListingLocationLabel(string $countryName, ?State $state, ?City $city): string
    {
        return collect([$city?->name, $state?->name, $countryName])
            ->filter()
            ->join(', ');
    }

    /**
     * Parsea los parámetros de URL en cascada usando los tipos configurados del país.
     * Retorna [TransactionType|null, PropertyType|null, State|null, City|null]
     */
    private function parseUrlParams(array $params, string $countryName, string $countryCode): array
    {
        $transactionType = null;
        $propertyType    = null;
        $state           = null;
        $city            = null;

        foreach ($params as $slug) {
            // 1. Intentar como tipo de operación
            if (!$transactionType && $result = PropertySlugHelper::getOperationBySlug($slug, $countryCode)) {
                $transactionType = $result;
                continue;
            }

            // 2. Intentar como tipo de propiedad
            if (!$propertyType && $result = PropertySlugHelper::getPropertyTypeBySlug($slug, $countryCode)) {
                $propertyType = $result;
                continue;
            }

            // 3. Intentar como estado/provincia (solo si aún no hay estado)
            if (!$state && $result = PropertySlugHelper::getStateBySlug($slug, $countryCode)) {
                $state = $result;
                continue;
            }

            // 4. Intentar como ciudad (requiere estado previo)
            if (!$city && $state && $result = PropertySlugHelper::getCityBySlug($slug, $state->id)) {
                $city = $result;
                continue;
            }

            // Slug no reconocido → 404
            abort(404, "Parámetro no válido en la URL: {$slug}");
        }

        return [$transactionType, $propertyType, $state, $city];
    }

    /**
     * Aplica filtros del sidebar (query parameters)
     */
    private function applySidebarFilters($query, Request $request)
    {
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('min_bedrooms')) {
            $query->where('bedrooms', '>=', $request->min_bedrooms);
        }
        if ($request->filled('min_bathrooms')) {
            $query->where('bathrooms', '>=', $request->min_bathrooms);
        }
        if ($request->filled('min_area')) {
            $query->where('area', '>=', $request->min_area);
        }
        if ($request->filled('max_area')) {
            $query->where('area', '<=', $request->max_area);
        }
        if ($request->filled('min_parking')) {
            $query->where('parking_spaces', '>=', $request->min_parking);
        }

        return $query;
    }

    /**
     * Aplica ordenamiento según parámetro sort
     */
    private function applySorting($query, Request $request)
    {
        switch ($request->get('sort', 'featured')) {
            case 'newest':
                return $query->orderBy('created_at', 'desc');
            case 'oldest':
                return $query->orderBy('created_at', 'asc');
            case 'price_asc':
                return $query->orderBy('price', 'asc');
            case 'price_desc':
                return $query->orderBy('price', 'desc');
            case 'area_asc':
                return $query->orderBy('area', 'asc');
            case 'area_desc':
                return $query->orderBy('area', 'desc');
            default:
                return $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
        }
    }

    /**
     * Genera metadata SEO dinámica usando los labels del país configurado.
     */
    private function generateSeoMetadata(
        string $countryName,
        string $countryCode,
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        ?State $state,
        ?City $city,
        int $total,
        string $locale
    ): array {
        $parts = [];

        // Tipo de propiedad: usar label_plural si existe
        if ($propertyType) {
            $parts[] = $propertyType->label_plural ?: $propertyType->label;
        } else {
            $parts[] = __('properties.properties', [], $locale);
        }

        // Tipo de operación: usar label
        if ($transactionType) {
            $parts[] = __('properties.for', [], $locale) . ' ' . strtolower($transactionType->label);
        }

        // Ubicación
        if ($city) {
            $parts[] = __('properties.in', [], $locale) . ' ' . $city->name;
        } elseif ($state) {
            $parts[] = __('properties.in', [], $locale) . ' ' . $state->name;
        } else {
            $parts[] = __('properties.in', [], $locale) . ' ' . $countryName;
        }

        $title = implode(' ', $parts);
        $description = trans_choice('properties.results.found', $total, ['count' => $total], $locale) . ' ' . $title;

        $alternateUrls = $this->generateAlternateUrls(
            $countryName, $transactionType, $propertyType, $state, $city
        );

        $hreflangTags = [];
        foreach ($alternateUrls as $lang => $url) {
            $hreflangTags[] = ['rel' => 'alternate', 'hreflang' => $lang, 'href' => $url];
        }
        $hreflangTags[] = ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $alternateUrls['es']];

        return [
            'title'         => $title,
            'description'   => substr($description, 0, 160),
            'image'         => url('/og_image.png'),
            'type'          => 'website',
            'canonical'     => url()->current(),
            'hreflang_tags' => $hreflangTags,
        ];
    }

    /**
     * Genera URLs alternativas para hreflang.
     * Ambos locales usan los mismos slugs (el `value` del país).
     */
    private function generateAlternateUrls(
        string $countryName,
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        ?State $state,
        ?City $city
    ): array {
        $path = '/' . PropertySlugHelper::normalize($countryName);

        if ($transactionType) {
            $path .= '/' . PropertySlugHelper::normalize($transactionType->value);
        }
        if ($propertyType) {
            $path .= '/' . PropertySlugHelper::normalize($propertyType->value);
        }
        if ($state) {
            $path .= '/' . PropertySlugHelper::normalize($state->name);
        }
        if ($city) {
            $path .= '/' . PropertySlugHelper::normalize($city->name);
        }

        return [
            'es' => url("/es{$path}"),
            'en' => url("/en{$path}"),
        ];
    }

    /**
     * Obtiene opciones disponibles para filtros del sidebar.
     */
    private function getFilterOptions(string $countryName, ?State $state, ?City $city): array
    {
        $query = PropertyListing::where('is_active', true)
            ->where('country', $countryName);

        if ($state) {
            $query->whereRaw('lower(unaccent(state)) = lower(unaccent(?))', [$state->name]);
        }
        if ($city) {
            $query->whereRaw('lower(unaccent(city)) = lower(unaccent(?))', [$city->name]);
        }

        return [
            'min_price'    => $query->min('price') ?? 0,
            'max_price'    => $query->max('price') ?? 1000000,
            'max_bedrooms' => $query->max('bedrooms') ?? 10,
            'max_bathrooms'=> $query->max('bathrooms') ?? 10,
            'max_area'     => $query->max('area') ?? 1000,
            'max_parking'  => $query->max('parking_spaces') ?? 10,
        ];
    }

    /**
     * Genera los enlaces de navegación del nivel siguiente del listado.
     *
     * La ruta canónica siempre conserva el orden operación, tipo, estado y
     * ciudad, aunque el usuario haya ingresado a un nivel intermedio.
     */
    private function getListingNavigationSections(
        string $countryName,
        string $countryCode,
        string $locale,
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        ?State $state,
        ?City $city
    ): array {
        $baseQuery = $this->getNavigationBaseQuery(
            $countryName,
            $transactionType,
            $propertyType,
            $state,
            $city
        );

        if ($city) {
            $sections = [];

            if (!$propertyType) {
                $sections[] = $this->makeNavigationSection(
                    __('properties.country_hub.property_types', [], $locale),
                    $this->getPropertyNavigationItems($baseQuery, $countryCode, $locale, $countryName, $transactionType, $state, $city),
                    'building'
                );
            }

            if (!$transactionType) {
                $sections[] = $this->makeNavigationSection(
                    __('properties.country_hub.transaction_types', [], $locale),
                    $this->getTransactionNavigationItems($baseQuery, $countryCode, $locale, $countryName, $propertyType, $state, $city),
                    'building'
                );
            }

            return $this->filterNavigationSections($sections);
        }

        if ($state) {
            $sections = [];

            if (!$propertyType) {
                $sections[] = $this->makeNavigationSection(
                    __('properties.country_hub.property_types', [], $locale),
                    $this->getPropertyNavigationItems($baseQuery, $countryCode, $locale, $countryName, $transactionType, $state),
                    'building'
                );
            }

            if (!$transactionType) {
                $sections[] = $this->makeNavigationSection(
                    __('properties.country_hub.transaction_types', [], $locale),
                    $this->getTransactionNavigationItems($baseQuery, $countryCode, $locale, $countryName, $propertyType, $state),
                    'building'
                );
            }

            $sections[] = $this->makeNavigationSection(
                __('properties.country_hub.cities', [], $locale),
                $this->getCityNavigationItems($baseQuery, $locale, $countryName, $transactionType, $propertyType, $state),
                'map-pin'
            );

            return $this->filterNavigationSections([
                ...$sections,
            ]);
        }

        if ($propertyType && !$transactionType) {
            return $this->filterNavigationSections([
                $this->makeNavigationSection(
                    __('properties.country_hub.transaction_types', [], $locale),
                    $this->getTransactionNavigationItems($baseQuery, $countryCode, $locale, $countryName, $propertyType),
                    'building'
                ),
            ]);
        }

        if ($transactionType && !$propertyType) {
            return $this->filterNavigationSections([
                $this->makeNavigationSection(
                    __('properties.country_hub.property_types', [], $locale),
                    $this->getPropertyNavigationItems($baseQuery, $countryCode, $locale, $countryName, $transactionType),
                    'building'
                ),
            ]);
        }

        if ($transactionType && $propertyType) {
            return $this->filterNavigationSections([
                $this->makeNavigationSection(
                    __('properties.country_hub.provinces', [], $locale),
                    $this->getStateNavigationItems($baseQuery, $countryCode, $locale, $countryName, $transactionType, $propertyType),
                    'map-pin'
                ),
            ]);
        }

        return $this->filterNavigationSections([
            $this->makeNavigationSection(
                __('properties.country_hub.property_types', [], $locale),
                $this->getPropertyNavigationItems($baseQuery, $countryCode, $locale, $countryName),
                'building'
            ),
            $this->makeNavigationSection(
                __('properties.country_hub.provinces', [], $locale),
                $this->getStateNavigationItems($baseQuery, $countryCode, $locale, $countryName),
                'map-pin'
            ),
        ]);
    }

    private function getNavigationBaseQuery(
        string $countryName,
        ?TransactionType $transactionType = null,
        ?PropertyType $propertyType = null,
        ?State $state = null,
        ?City $city = null
    ): Builder {
        $query = PropertyListing::query()
            ->where('is_active', true)
            ->where('country', $countryName);

        if ($transactionType) {
            $query->whereRaw('LOWER(TRIM(transaction_type)) = LOWER(TRIM(?))', [$transactionType->value]);
        }

        if ($propertyType) {
            $query->whereRaw('LOWER(TRIM(property_type)) = LOWER(TRIM(?))', [$propertyType->value]);
        }

        if ($state) {
            $query->whereRaw('LOWER(unaccent(TRIM(state))) = LOWER(unaccent(TRIM(?)))', [$state->name]);
        }

        if ($city) {
            $query->whereRaw('LOWER(unaccent(TRIM(city))) = LOWER(unaccent(TRIM(?)))', [$city->name]);
        }

        return $query;
    }

    private function getTransactionNavigationItems(
        Builder $baseQuery,
        string $countryCode,
        string $locale,
        string $countryName,
        ?PropertyType $propertyType = null,
        ?State $state = null,
        ?City $city = null
    ): array {
        $counts = (clone $baseQuery)
            ->whereNotNull('transaction_type')
            ->whereRaw("TRIM(transaction_type) != ''")
            ->selectRaw('LOWER(TRIM(transaction_type)) as filter_value, COUNT(*) as total')
            ->groupByRaw('LOWER(TRIM(transaction_type))')
            ->pluck('total', 'filter_value');

        return TransactionType::getByCountry($countryCode)
            ->filter(fn(TransactionType $type): bool => $counts->has(strtolower(trim($type->value))))
            ->map(fn(TransactionType $type): array => [
                'label' => $type->label,
                'count' => (int) $counts->get(strtolower(trim($type->value))),
                'url' => $this->buildListingUrl($locale, $countryName, $type, $propertyType, $state, $city),
            ])
            ->values()
            ->all();
    }

    private function getPropertyNavigationItems(
        Builder $baseQuery,
        string $countryCode,
        string $locale,
        string $countryName,
        ?TransactionType $transactionType = null,
        ?State $state = null,
        ?City $city = null
    ): array {
        $counts = (clone $baseQuery)
            ->whereNotNull('property_type')
            ->whereRaw("TRIM(property_type) != ''")
            ->selectRaw('LOWER(TRIM(property_type)) as filter_value, COUNT(*) as total')
            ->groupByRaw('LOWER(TRIM(property_type))')
            ->pluck('total', 'filter_value');

        return PropertyType::getByCountry($countryCode)
            ->filter(fn(PropertyType $type): bool => $counts->has(strtolower(trim($type->value))))
            ->map(fn(PropertyType $type): array => [
                'label' => $type->label_plural ?: $type->label,
                'count' => (int) $counts->get(strtolower(trim($type->value))),
                'url' => $this->buildListingUrl($locale, $countryName, $transactionType, $type, $state, $city),
            ])
            ->values()
            ->all();
    }

    private function getStateNavigationItems(
        Builder $baseQuery,
        string $countryCode,
        string $locale,
        string $countryName,
        ?TransactionType $transactionType = null,
        ?PropertyType $propertyType = null
    ): array {
        $listingStates = (clone $baseQuery)
            ->whereNotNull('state')
            ->whereRaw("TRIM(state) != ''")
            ->selectRaw('MAX(TRIM(state)) as state_name, COUNT(*) as total')
            ->groupByRaw('LOWER(unaccent(TRIM(state)))')
            ->orderByRaw('LOWER(unaccent(TRIM(state)))')
            ->get();

        return $listingStates->map(function (PropertyListing $row) use ($countryCode, $locale, $countryName, $transactionType, $propertyType): ?array {
            $stateSlug = PropertySlugHelper::normalize($row->state_name);
            $worldState = PropertySlugHelper::getStateBySlug($stateSlug, $countryCode);

            if (!$worldState) {
                return null;
            }

            return [
                'label' => $worldState->name,
                'count' => (int) $row->total,
                'url' => $this->buildListingUrl($locale, $countryName, $transactionType, $propertyType, $worldState),
            ];
        })->filter()->values()->all();
    }

    private function getCityNavigationItems(
        Builder $baseQuery,
        string $locale,
        string $countryName,
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        State $state
    ): array {
        $listingCities = (clone $baseQuery)
            ->whereNotNull('city')
            ->whereRaw("TRIM(city) != ''")
            ->selectRaw('MAX(TRIM(city)) as city_name, COUNT(*) as total')
            ->groupByRaw('LOWER(unaccent(TRIM(city)))')
            ->orderByRaw('LOWER(unaccent(TRIM(city)))')
            ->get();

        return $listingCities->map(function (PropertyListing $row) use ($locale, $countryName, $transactionType, $propertyType, $state): ?array {
            $citySlug = PropertySlugHelper::normalize($row->city_name);
            $worldCity = PropertySlugHelper::getCityBySlug($citySlug, $state->id);

            if (!$worldCity) {
                return null;
            }

            return [
                'label' => $worldCity->name,
                'count' => (int) $row->total,
                'url' => $this->buildListingUrl($locale, $countryName, $transactionType, $propertyType, $state, $worldCity),
            ];
        })->filter()->values()->all();
    }

    private function buildListingUrl(
        string $locale,
        string $countryName,
        ?TransactionType $transactionType = null,
        ?PropertyType $propertyType = null,
        ?State $state = null,
        ?City $city = null
    ): string {
        $segments = [$locale, PropertySlugHelper::normalize($countryName)];

        foreach ([$transactionType?->value, $propertyType?->value, $state?->name, $city?->name] as $value) {
            if ($value !== null) {
                $segments[] = PropertySlugHelper::normalize($value);
            }
        }

        return url('/' . implode('/', $segments));
    }

    private function makeNavigationSection(string $title, array $items, string $icon): array
    {
        return [
            'title' => $title,
            'items' => $items,
            'columns' => 3,
            'icon' => $icon,
        ];
    }

    private function filterNavigationSections(array $sections): array
    {
        return array_values(array_filter($sections, fn(array $section): bool => !empty($section['items'])));
    }
}
