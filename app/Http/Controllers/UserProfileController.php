<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PropertyListing;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class UserProfileController extends Controller
{
    /**
     * Muestra el perfil público de un usuario con sus anuncios
     * 
     * URL: /{locale}/inmobiliaria/{username} o /{locale}/realtor/{username}
     */
    public function show(Request $request, string $locale, string $username)
    {
        // Establecer locale
        App::setLocale($locale);

        // Buscar usuario por username
        $user = User::where('username', $username)->firstOrFail();

        // Construir query de propiedades activas del usuario
        $query = PropertyListing::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['primaryImage', 'images']);

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

        // Generar breadcrumbs
        $breadcrumbs = $this->generateBreadcrumbs($user, $locale);

        // Generar SEO
        $seo = $this->generateSeo($user, $properties->total(), $locale);

        // Estadísticas del usuario
        $stats = [
            'total_active' => PropertyListing::where('user_id', $user->id)
                ->where('is_active', true)
                ->count(),
            'total_sales' => PropertyListing::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('transaction_type', 'sale')
                ->count(),
            'total_rentals' => PropertyListing::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('transaction_type', 'rent')
                ->count(),
        ];

        return view('user-profile', compact('user', 'properties', 'breadcrumbs', 'seo', 'stats'));
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
                'url' => null
            ],
            [
                'label' => $user->agency ?: $user->name,
                'url' => null
            ]
        ];

        return $breadcrumbs;
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
            ? asset('storage/' . $user->avatar) 
            : asset('images/default-avatar.png');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonicalUrl,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_type' => 'profile',
            'hreflang' => [
                'es' => route('user.profile.es', ['locale' => 'es', 'username' => $user->username]),
                'en' => route('user.profile.en', ['locale' => 'en', 'username' => $user->username]),
            ]
        ];
    }
}
