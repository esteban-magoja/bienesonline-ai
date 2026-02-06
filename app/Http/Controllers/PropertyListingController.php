<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Helpers\PropertySlugHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PropertyListingController extends Controller
{
    /**
     * Muestra listados de propiedades con URLs amigables SEO
     * 
     * Estructura de URL:
     * /{locale}/{país}/{operación?}/{tipo?}/{estado?}/{ciudad?}
     * 
     * Ejemplos:
     * /es/argentina
     * /es/argentina/venta
     * /es/argentina/venta/casas
     * /es/argentina/venta/casas/cordoba
     * /es/argentina/venta/casas/cordoba/villa-maria
     */
    public function index(Request $request, string $locale, string $country, string $params = null)
    {
        // Establecer locale
        App::setLocale($locale);

        // Validar que el país exista en la BD
        $countryName = PropertySlugHelper::validateCountry($country);
        
        if (!$countryName) {
            abort(404, "País no encontrado: {$country}");
        }

        // Variables de contexto
        $filters = [
            'country' => $countryName,
            'transaction_type' => null,
            'property_type' => null,
            'state' => null,
            'city' => null,
        ];

        // Dividir params en array si existe
        $paramsArray = $params ? explode('/', trim($params, '/')) : [];

        // Parsear parámetros opcionales en cascada
        $filters = $this->parseUrlParams($paramsArray, $filters);

        // Construir query base
        $query = PropertyListing::where('is_active', true)
            ->where('country', $filters['country']);

        // Aplicar filtros de URL
        if ($filters['transaction_type']) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if ($filters['property_type']) {
            $query->where('property_type', $filters['property_type']);
        }

        if ($filters['state']) {
            $query->where('state', $filters['state']);
        }

        if ($filters['city']) {
            $query->where('city', $filters['city']);
        }

        // Aplicar filtros de sidebar (query params)
        $query = $this->applySidebarFilters($query, $request);

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request);

        // Paginación
        $properties = $query->with(['user', 'primaryImage'])->paginate(20)->withQueryString();

        // Generar breadcrumbs
        $breadcrumbs = PropertySlugHelper::generateBreadcrumbs(
            $locale,
            $filters['country'],
            $filters['transaction_type'],
            $filters['property_type'],
            $filters['state'],
            $filters['city']
        );

        // Generar metadata SEO
        $seo = $this->generateSeoMetadata($filters, $properties->total(), $locale);

        // Obtener opciones de filtros disponibles para el sidebar
        $filterOptions = $this->getFilterOptions($filters);

        return view('property-listing', compact(
            'properties',
            'filters',
            'breadcrumbs',
            'seo',
            'filterOptions',
            'locale'
        ));
    }

    /**
     * Parsea los parámetros de URL en cascada
     * Detecta automáticamente si son transaction, property type, state o city
     */
    private function parseUrlParams(array $params, array $filters): array
    {
        if (empty($params)) {
            return $filters;
        }

        $country = $filters['country'];
        $contextState = null; // Para validación de ciudad

        foreach ($params as $index => $slug) {
            // Detectar tipo de parámetro
            $detected = PropertySlugHelper::detectSlugType($slug, $country, $contextState);

            if ($detected['type'] === 'unknown') {
                abort(404, "Parámetro no válido: {$slug}");
            }

            // Asignar según tipo detectado
            switch ($detected['type']) {
                case 'transaction':
                    if ($filters['transaction_type'] === null) {
                        $filters['transaction_type'] = $detected['value'];
                    } else {
                        abort(404, "Tipo de transacción duplicado");
                    }
                    break;

                case 'property':
                    if ($filters['property_type'] === null) {
                        $filters['property_type'] = $detected['value'];
                    } else {
                        abort(404, "Tipo de propiedad duplicado");
                    }
                    break;

                case 'state':
                    if ($filters['state'] === null) {
                        $filters['state'] = $detected['value'];
                        $contextState = $detected['value']; // Guardar para validar ciudades
                    } else {
                        abort(404, "Estado duplicado");
                    }
                    break;

                case 'city':
                    if ($filters['city'] === null) {
                        $filters['city'] = $detected['value'];
                    } else {
                        abort(404, "Ciudad duplicada");
                    }
                    break;
            }
        }

        return $filters;
    }

    /**
     * Aplica filtros del sidebar (query parameters)
     */
    private function applySidebarFilters($query, Request $request)
    {
        // Filtro de precio
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filtro de habitaciones
        if ($request->filled('min_bedrooms')) {
            $query->where('bedrooms', '>=', $request->min_bedrooms);
        }

        // Filtro de baños
        if ($request->filled('min_bathrooms')) {
            $query->where('bathrooms', '>=', $request->min_bathrooms);
        }

        // Filtro de área cubierta
        if ($request->filled('min_area')) {
            $query->where('area', '>=', $request->min_area);
        }

        if ($request->filled('max_area')) {
            $query->where('area', '<=', $request->max_area);
        }

        // Filtro de cocheras
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
        $sort = $request->get('sort', 'featured');

        switch ($sort) {
            case 'featured':
                $query->orderBy('is_featured', 'desc')
                      ->orderBy('created_at', 'desc');
                break;

            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;

            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;

            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;

            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;

            case 'area_asc':
                $query->orderBy('area', 'asc');
                break;

            case 'area_desc':
                $query->orderBy('area', 'desc');
                break;

            default:
                $query->orderBy('is_featured', 'desc')
                      ->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * Genera metadata SEO dinámica completa
     */
    private function generateSeoMetadata(array $filters, int $total, string $locale): array
    {
        $parts = [];

        // Construir título dinámico
        if ($filters['property_type']) {
            $parts[] = __("properties.types.{$filters['property_type']}", [], $locale);
        } else {
            $parts[] = __('properties.properties', [], $locale);
        }

        if ($filters['transaction_type']) {
            $parts[] = __('properties.for', [], $locale) . ' ' . __("properties.transaction_types.{$filters['transaction_type']}", [], $locale);
        }

        if ($filters['city']) {
            $parts[] = __('properties.in', [], $locale) . ' ' . $filters['city'];
        } elseif ($filters['state']) {
            $parts[] = __('properties.in', [], $locale) . ' ' . $filters['state'];
        } else {
            $parts[] = __('properties.in', [], $locale) . ' ' . $filters['country'];
        }

        $title = implode(' ', $parts);

        // Construir descripción
        $description = trans_choice('properties.results.found', $total, ['count' => $total], $locale) . ' ' . $title;
        
        // URL actual
        $currentUrl = url()->current();
        
        // URL alternativas para hreflang
        $alternateUrls = $this->generateAlternateUrls($filters, $locale);
        
        // Formatear hreflang_tags según lo esperado por el layout
        $hreflangTags = [];
        foreach ($alternateUrls as $lang => $url) {
            $hreflangTags[] = [
                'rel' => 'alternate',
                'hreflang' => $lang,
                'href' => $url
            ];
        }
        // Agregar x-default
        $hreflangTags[] = [
            'rel' => 'alternate',
            'hreflang' => 'x-default',
            'href' => $alternateUrls['es']
        ];

        // Imagen Open Graph (usar primera propiedad si hay resultados)
        $ogImage = url('/og_image.png'); // Imagen por defecto
        
        return [
            'title' => $title,
            'description' => substr($description, 0, 160),
            'image' => $ogImage,
            'type' => 'website',
            'canonical' => $currentUrl,
            'hreflang_tags' => $hreflangTags,
        ];
    }

    /**
     * Genera URLs alternativas para hreflang
     */
    private function generateAlternateUrls(array $filters, string $currentLocale): array
    {
        $locales = ['es', 'en'];
        $alternates = [];
        
        // Construir path base
        $path = '/' . PropertySlugHelper::normalize($filters['country']);
        
        if ($filters['transaction_type']) {
            // Mapear al slug correcto según idioma
            $transactionSlugs = [
                'sale' => ['es' => 'venta', 'en' => 'sale'],
                'rent' => ['es' => 'alquiler', 'en' => 'rent'],
                'temporary_rent' => ['es' => 'alquiler-temporal', 'en' => 'temporary-rent'],
            ];
            $transSlug = $transactionSlugs[$filters['transaction_type']] ?? null;
        }
        
        if ($filters['property_type']) {
            // Mapear al slug correcto según idioma
            $propertySlugs = [
                'house' => ['es' => 'casas', 'en' => 'houses'],
                'apartment' => ['es' => 'departamentos', 'en' => 'apartments'],
                'office' => ['es' => 'oficinas', 'en' => 'offices'],
                'commercial' => ['es' => 'locales', 'en' => 'commercials'],
                'land' => ['es' => 'terrenos', 'en' => 'lands'],
                'field' => ['es' => 'campos', 'en' => 'fields'],
                'farm' => ['es' => 'fincas', 'en' => 'farms'],
                'warehouse' => ['es' => 'galpones', 'en' => 'warehouses'],
            ];
            $propSlug = $propertySlugs[$filters['property_type']] ?? null;
        }
        
        foreach ($locales as $locale) {
            $urlPath = "/{$locale}{$path}";
            
            if (isset($transSlug)) {
                $urlPath .= '/' . $transSlug[$locale];
            }
            
            if (isset($propSlug)) {
                $urlPath .= '/' . $propSlug[$locale];
            }
            
            if ($filters['state']) {
                $urlPath .= '/' . PropertySlugHelper::normalize($filters['state']);
            }
            
            if ($filters['city']) {
                $urlPath .= '/' . PropertySlugHelper::normalize($filters['city']);
            }
            
            $alternates[$locale] = url($urlPath);
        }
        
        return $alternates;
    }

    /**
     * Obtiene opciones disponibles para filtros del sidebar
     * Basado en el contexto actual (país, estado, etc.)
     */
    private function getFilterOptions(array $filters): array
    {
        $query = PropertyListing::where('is_active', true)
            ->where('country', $filters['country']);

        // Si hay filtros de ubicación, aplicarlos
        if ($filters['state']) {
            $query->where('state', $filters['state']);
        }

        if ($filters['city']) {
            $query->where('city', $filters['city']);
        }

        // Obtener rangos disponibles
        return [
            'min_price' => $query->min('price') ?? 0,
            'max_price' => $query->max('price') ?? 1000000,
            'max_bedrooms' => $query->max('bedrooms') ?? 10,
            'max_bathrooms' => $query->max('bathrooms') ?? 10,
            'max_area' => $query->max('area') ?? 1000,
            'max_parking' => $query->max('parking_spaces') ?? 10,
        ];
    }
}
