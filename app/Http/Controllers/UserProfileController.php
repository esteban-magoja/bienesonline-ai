<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Services\ListingMapService;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    /** Query params de filtros del listado del perfil (ver profileListingsQuery()). */
    private const PROFILE_FILTERS = [
        'country', 'state', 'city', 'transaction_type', 'property_type',
        'min_price', 'max_price', 'bedrooms', 'bathrooms', 'min_area',
    ];

    /**
     * Muestra el perfil público de un usuario con sus anuncios
     * 
     * URL: /{locale}/inmobiliaria/{username} o /{locale}/realtor/{username}
     */
    public function show(Request $request, ListingMapService $listingMap, string $locale, string $username): View
    {
        // Establecer locale
        App::setLocale($locale);

        // Buscar usuario por username
        $user = User::query()
            ->with([
                'profileSetting',
                'profileServices' => fn ($query) => $query->where('is_active', true),
                'profileMembers' => fn ($query) => $query->where('is_visible', true),
            ])
            ->where('username', $username)
            ->firstOrFail();
        $selectedCountry = trim($request->string('country')->toString());
        $selectedState = trim($request->string('state')->toString());
        $selectedCity = trim($request->string('city')->toString());

        // Query de propiedades activas del usuario con los filtros del query string
        $query = $this->profileListingsQuery($user, $request);

        if ($request->query('view') === 'map') {
            return $this->showMap($request, $listingMap, $locale, $user, $query);
        }

        // Anuncios con ubicación: si no hay ninguno no se ofrece la vista de mapa
        $mapUrl = $listingMap->onlyMappable(clone $query)->exists()
            ? $this->profileUrl($user, $locale, [...$request->except(['page', 'view']), 'view' => 'map'])
            : null;

        $query = $this->applyProfileSorting($query->with(['primaryImage', 'firstImage']), $request);

        // Paginación
        $properties = $query->paginate(12)->withQueryString();

        // Generar breadcrumbs
        $breadcrumbs = $this->generateBreadcrumbs($user, $locale);
        $companyDescription = $user->profile('about');
        $profileSetting = $user->profileSetting;

        // Generar SEO
        $seo = $this->generateSeo($user, $properties->total(), $locale);

        // Estadísticas del usuario: contar usando valores equivalentes (multi-país)
        $saleValues  = TransactionType::getEquivalentValues('sale', 'INTL');
        $rentValues  = TransactionType::getEquivalentValues('rent', 'INTL');

        $statsQuery = PropertyListing::query()
            ->where('user_id', $user->id)
            ->where('is_active', true);

        $stats = [
            'total_active' => (clone $statsQuery)->count(),
            'total_sales' => (clone $statsQuery)->whereIn('transaction_type', $saleValues)->count(),
            'total_rentals' => (clone $statsQuery)->whereIn('transaction_type', $rentValues)->count(),
        ];

        // Tipos disponibles en los anuncios de este usuario (para filtros dinámicos)
        $userPropertyTypes     = PropertyListing::where('user_id', $user->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('property_type');
        $userTransactionTypes  = PropertyListing::where('user_id', $user->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('transaction_type');

        $locationOptions = $this->getLocationOptions($user->id);

        return view('user-profile', compact(
            'user',
            'properties',
            'breadcrumbs',
            'seo',
            'stats',
            'userPropertyTypes',
            'userTransactionTypes',
            'companyDescription',
            'profileSetting',
            'locationOptions',
            'selectedCountry',
            'selectedState',
            'selectedCity',
            'mapUrl'
        ));
    }

    /**
     * Vista de mapa de los anuncios del perfil (misma vista que los listados SEO), sin indexar.
     */
    private function showMap(Request $request, ListingMapService $listingMap, string $locale, User $user, Builder $query): View
    {
        $total = (clone $query)->count();
        $mappableCount = $listingMap->onlyMappable(clone $query)->count();
        $displayName = $user->agency ?: $user->name;
        $profileUrl = $this->profileUrl($user, $locale);

        $seo = $this->generateSeo($user, $total, $locale);
        $seo['title'] = __('properties.map_view.page_title', [
            'title' => __('properties.user_profile.title', ['name' => $displayName]),
        ]);
        $seo['robots'] = 'noindex,follow';
        unset($seo['structured_data'], $seo['hreflang']);

        $breadcrumbs = $this->generateBreadcrumbs($user, $locale);
        $breadcrumbs[array_key_last($breadcrumbs)]['url'] = $profileUrl;
        $breadcrumbs[] = ['label' => __('properties.map_view.show_map'), 'url' => null];

        $navigationSections = array_values(array_filter([
            $this->mapNavigationSection($request, $user, $locale, 'transaction_type', __('properties.country_hub.transaction_types'),
                fn (string $value): string => TransactionType::getLabel($value, $locale)),
            $this->mapNavigationSection($request, $user, $locale, 'property_type', __('properties.country_hub.property_types'),
                fn (string $value): string => PropertyType::getLabel($value, $locale)),
            $this->mapNavigationSection($request, $user, $locale, 'state', __('properties.country_hub.provinces'),
                fn (string $value): string => $value),
        ]));

        $listQuery = $request->except(['view', 'lat', 'lng', 'zoom']);
        $listUrl = $this->profileUrl($user, $locale, $listQuery) . '#propiedades';

        $mapConfig = [
            'dataUrl' => route('user.profile.map-data', ['locale' => $locale, 'username' => $user->username]),
            'params' => array_filter(
                $request->only([...self::PROFILE_FILTERS, 'sort', 'order']),
                fn ($value) => $value !== null && $value !== ''
            ),
            'center' => $listingMap->parseMapCenter($request),
            'locale' => $locale,
        ];

        return view('property-listing-map', compact(
            'seo',
            'breadcrumbs',
            'navigationSections',
            'total',
            'mappableCount',
            'listUrl',
            'mapConfig',
            'locale'
        ));
    }

    /**
     * Endpoint JSON con los anuncios ubicables del perfil (mismo formato que los listados SEO).
     */
    public function mapData(Request $request, ListingMapService $listingMap, string $locale, string $username): JsonResponse
    {
        App::setLocale($locale);

        $user = User::query()->where('username', $username)->firstOrFail();

        return $listingMap->response(
            $this->profileListingsQuery($user, $request),
            $request,
            $locale,
            fn (Builder $query): Builder => $this->applyProfileSorting($query, $request),
            'map_data_profile_' . md5(json_encode([
                $locale,
                $user->id,
                $request->only([...self::PROFILE_FILTERS, 'sort', 'order']),
            ]))
        );
    }

    /**
     * Select de navegación del mapa para un filtro del perfil (operación, tipo, provincia),
     * con cantidades calculadas sobre el resto de los filtros activos. Null si no aporta nada.
     *
     * @param  callable(string): string  $labelFor
     * @return array{title: string, all_url: string, items: array<int, array{label: string, url: string, count: int, selected: bool}>}|null
     */
    private function mapNavigationSection(Request $request, User $user, string $locale, string $param, string $title, callable $labelFor): ?array
    {
        $otherFilters = $request->except([$param, 'view', 'lat', 'lng', 'zoom', 'page']);
        $selected = trim((string) $request->query($param));

        $rows = $this->profileListingsQuery($user, $request->duplicate($otherFilters))
            ->whereNotNull($param)
            ->whereRaw("TRIM({$param}) != ''")
            ->selectRaw("{$param} as value, COUNT(*) as total")
            ->groupBy($param)
            ->orderByDesc('total')
            ->get();

        if ($rows->count() < 2 && $selected === '') {
            return null;
        }

        return [
            'title' => $title,
            'all_url' => $this->profileUrl($user, $locale, [...$otherFilters, 'view' => 'map']),
            'items' => $rows->map(fn ($row): array => [
                'label' => $labelFor($row->value),
                'url' => $this->profileUrl($user, $locale, [...$otherFilters, $param => $row->value, 'view' => 'map']),
                'count' => (int) $row->total,
                'selected' => strcasecmp($selected, trim($row->value)) === 0,
            ])->all(),
        ];
    }

    /**
     * Anuncios activos del usuario con los filtros del query string del perfil, sin ordenar.
     */
    private function profileListingsQuery(User $user, Request $request): Builder
    {
        $query = PropertyListing::where('user_id', $user->id)
            ->where('is_active', true);

        foreach (['country', 'state', 'city'] as $location) {
            $value = trim($request->string($location)->toString());
            if ($value !== '') {
                $query->whereRaw("LOWER(TRIM({$location})) = LOWER(TRIM(?))", [$value]);
            }
        }

        // Aplicar filtros de query string
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', $request->bedrooms);
        }

        if ($request->filled('bathrooms')) {
            $query->where('bathrooms', '>=', $request->bathrooms);
        }

        if ($request->filled('min_area')) {
            $query->where('area', '>=', $request->min_area);
        }

        return $query;
    }

    private function applyProfileSorting(Builder $query, Request $request): Builder
    {
        $sortBy = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');

        $validSorts = ['created_at', 'price', 'area', 'bedrooms'];
        $validOrders = ['asc', 'desc'];

        if (in_array($sortBy, $validSorts) && in_array($sortOrder, $validOrders)) {
            return $query->orderBy($sortBy, $sortOrder);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function profileUrl(User $user, string $locale, array $query = []): string
    {
        $routeName = $locale === 'es' ? 'user.profile.es' : 'user.profile.en';
        $url = route($routeName, ['locale' => $locale, 'username' => $user->username]);

        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    private function getLocationOptions(int $userId): array
    {
        return PropertyListing::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNotNull('country')
            ->whereRaw("TRIM(country) != ''")
            ->selectRaw('DISTINCT TRIM(country) as country, TRIM(state) as state, TRIM(city) as city')
            ->orderBy('country')
            ->orderBy('state')
            ->orderBy('city')
            ->get()
            ->map(fn (PropertyListing $listing): array => [
                'country' => $listing->country,
                'state' => $listing->state,
                'city' => $listing->city,
            ])
            ->values()
            ->all();
    }

    /**
     * Genera breadcrumbs para el perfil de usuario
     */
    private function generateBreadcrumbs(User $user, string $locale): array
    {
        $breadcrumbs = [
            [
                'label' => __('messages.home'),
                'url' => route('home', ['locale' => $locale])
            ],
            [
                'label' => __('properties.user_profile.realtors'),
                'url' => $this->getDirectoryUrl($user, $locale)
            ],
            [
                'label' => $user->agency ?: $user->name,
                'url' => null
            ]
        ];

        return $breadcrumbs;
    }

    private function getDirectoryUrl(User $user, string $locale): string
    {
        $routeName = $locale === 'es' ? 'agents.directory.es' : 'agents.directory.en';

        if (filled($user->country)) {
            $routeName = $locale === 'es'
                ? 'agents.directory.es.location'
                : 'agents.directory.en.location';

            return route($routeName, [
                'locale' => $locale,
                'country' => \App\Helpers\PropertySlugHelper::normalize($user->country),
            ]);
        }

        return route($routeName, ['locale' => $locale]);
    }

    /**
     * Genera meta tags SEO para el perfil
     */
    private function generateSeo(User $user, int $totalProperties, string $locale): array
    {
        $displayName = $user->agency ?: $user->name;
        
        // Construir ubicación
        $location = collect([$user->city, $user->state, $user->country])
            ->filter()
            ->join(', ');

        // Title
        $title = __('properties.user_profile.title', ['name' => $displayName]);

        // Description
        $description = __('properties.user_profile.description', [
            'count' => $totalProperties,
            'name' => $displayName,
            'location' => $location ?: __('properties.user_profile.various_locations')
        ]);

        // Truncar description a 160 caracteres
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }

        // URL canónica
        $currentLocale = App::getLocale();
        $routeName = $currentLocale === 'es' ? 'user.profile.es' : 'user.profile.en';
        $canonicalUrl = route($routeName, ['locale' => $currentLocale, 'username' => $user->username]);

        // Imagen (avatar del usuario o fallback)
        $ogImage = $user->avatar 
            ? $user->avatar() 
            : asset('images/default-avatar.png');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonicalUrl,
            'image' => $ogImage,
            'type' => 'profile',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_type' => 'profile',
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => $user->agency ? 'RealEstateAgent' : 'Person',
                'name' => $displayName,
                'url' => $canonicalUrl,
                'image' => $ogImage,
                'email' => ($user->profileSetting?->show_email ?? true) ? $user->email : null,
                'telephone' => ($user->profileSetting?->show_phone ?? true) ? $user->movil : null,
                'address' => ($user->profileSetting?->show_address ?? true) && $location
                    ? [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $user->city,
                        'addressRegion' => $user->state,
                        'addressCountry' => $user->country,
                    ]
                    : null,
                'sameAs' => array_values(array_filter($user->profileSetting?->social_links ?? [])),
            ],
            'hreflang' => [
                'es' => route('user.profile.es', ['locale' => 'es', 'username' => $user->username]),
                'en' => route('user.profile.en', ['locale' => 'en', 'username' => $user->username]),
            ]
        ];
    }
}
