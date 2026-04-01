<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Helpers\PropertySlugHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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

        // Hub de categorías (solo cuando no hay filtros aplicados)
        $countryHubSections = $this->shouldShowCountryHub($transactionType, $propertyType, $state, $city)
            ? $this->getCountryHubSections($countryName, $countryCode, $locale)
            : [];

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
            'locale'
        ));
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

    private function shouldShowCountryHub(
        ?TransactionType $transactionType,
        ?PropertyType $propertyType,
        ?State $state,
        ?City $city
    ): bool {
        return !$transactionType && !$propertyType && !$state && !$city;
    }

    /**
     * Genera secciones del hub de categorías para la página de país.
     * Usa los tipos configurados en DB, sin mapeos ni equivalencias.
     */
    private function getCountryHubSections(string $countryName, string $countryCode, string $locale): array
    {
        $countrySlug = PropertySlugHelper::normalize($countryName);
        $baseQuery   = PropertyListing::where('is_active', true)->where('country', $countryName);

        // --- Tipos de operación ---
        $transactionItems = [];
        foreach (TransactionType::getByCountry($countryCode) as $type) {
            $count = (clone $baseQuery)
                ->whereRaw('LOWER(transaction_type) = LOWER(?)', [$type->value])
                ->count();

            if ($count > 0) {
                $transactionItems[] = [
                    'label' => $type->label,
                    'count' => $count,
                    'url'   => url("/{$locale}/{$countrySlug}/" . PropertySlugHelper::normalize($type->value)),
                ];
            }
        }

        // --- Tipos de propiedad ---
        $propertyItems = [];
        foreach (PropertyType::getByCountry($countryCode) as $type) {
            $count = (clone $baseQuery)
                ->whereRaw('LOWER(property_type) = LOWER(?)', [$type->value])
                ->count();

            if ($count > 0) {
                $propertyItems[] = [
                    'label' => $type->label_plural ?: $type->label,
                    'count' => $count,
                    'url'   => url("/{$locale}/{$countrySlug}/" . PropertySlugHelper::normalize($type->value)),
                ];
            }
        }

        // --- Estados/Provincias (solo los que están en nnjeim/world) ---
        $stateItems = [];
        $listingStates = (clone $baseQuery)
            ->whereNotNull('state')
            ->whereRaw("TRIM(state) != ''")
            ->selectRaw('TRIM(state) as state_name, COUNT(*) as total')
            ->groupByRaw('TRIM(state)')
            ->orderBy('state_name')
            ->get();

        foreach ($listingStates as $row) {
            $stateSlug  = PropertySlugHelper::normalize($row->state_name);
            $worldState = PropertySlugHelper::getStateBySlug($stateSlug, $countryCode);

            // Solo incluir estados validados contra nnjeim/world
            if ($worldState) {
                $stateItems[] = [
                    'label' => $worldState->name,
                    'count' => (int) $row->total,
                    'url'   => url("/{$locale}/{$countrySlug}/{$stateSlug}"),
                ];
            }
        }

        return array_values(array_filter([
            [
                'title'   => __('properties.country_hub.property_types', [], $locale),
                'items'   => $propertyItems,
                'columns' => 3,
                'icon'    => 'building',
            ],
            [
                'title'   => __('properties.country_hub.provinces', [], $locale),
                'items'   => $stateItems,
                'columns' => 3,
                'icon'    => 'map-pin',
            ],
        ], fn(array $s) => !empty($s['items'])));
    }
}
