<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PropertyListing;
use App\Models\TransactionType;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    /**
     * Muestra el perfil público de un usuario con sus anuncios
     * 
     * URL: /{locale}/inmobiliaria/{username} o /{locale}/realtor/{username}
     */
    public function show(Request $request, string $locale, string $username): View
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

        // Construir query de propiedades activas del usuario
        $query = PropertyListing::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['primaryImage', 'firstImage']);

        if ($selectedCountry !== '') {
            $query->whereRaw('LOWER(TRIM(country)) = LOWER(TRIM(?))', [$selectedCountry]);
        }

        if ($selectedState !== '') {
            $query->whereRaw('LOWER(TRIM(state)) = LOWER(TRIM(?))', [$selectedState]);
        }

        if ($selectedCity !== '') {
            $query->whereRaw('LOWER(TRIM(city)) = LOWER(TRIM(?))', [$selectedCity]);
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

        // Ordenamiento
        $sortBy = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');

        $validSorts = ['created_at', 'price', 'area', 'bedrooms'];
        $validOrders = ['asc', 'desc'];

        if (in_array($sortBy, $validSorts) && in_array($sortOrder, $validOrders)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginación
        $properties = $query->paginate(12)->withQueryString();

        $applySelectedFilters = function ($featuredQuery) use ($selectedCountry, $selectedState, $selectedCity, $request): void {
            if ($selectedCountry !== '') {
                $featuredQuery->whereRaw('LOWER(TRIM(country)) = LOWER(TRIM(?))', [$selectedCountry]);
            }

            if ($selectedState !== '') {
                $featuredQuery->whereRaw('LOWER(TRIM(state)) = LOWER(TRIM(?))', [$selectedState]);
            }

            if ($selectedCity !== '') {
                $featuredQuery->whereRaw('LOWER(TRIM(city)) = LOWER(TRIM(?))', [$selectedCity]);
            }

            foreach (['transaction_type', 'property_type'] as $filter) {
                if ($request->filled($filter)) {
                    $featuredQuery->where($filter, $request->input($filter));
                }
            }
        };

        $featuredProperties = PropertyListing::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_featured', true)
            ->tap($applySelectedFilters)
            ->with(['primaryImage', 'firstImage'])
            ->latest()
            ->limit(6)
            ->get();

        if ($featuredProperties->isEmpty()) {
            $featuredProperties = PropertyListing::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->tap($applySelectedFilters)
                ->with(['primaryImage', 'firstImage'])
                ->latest()
                ->limit(6)
                ->get();
        }

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
            'featuredProperties',
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
            'selectedCity'
        ));
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
