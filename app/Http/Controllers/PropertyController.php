<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PropertyListing;
use App\Models\PropertyMessage;
use App\Mail\PropertyMessageReceived;
use App\Services\SeoService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    protected $seoService;

    public function __construct(SeoService $seoService)
    {
        $this->seoService = $seoService;
    }

    /**
     * Display the specified property listing.
     * Nueva estructura: /{locale}/{country}/{city}/propiedad/{id}-{slug}
     */
    public function show($locale, $country, $city, $id, $slug = null)
    {
        $property = PropertyListing::with(['user', 'images'])
            ->where('is_active', true)
            ->findOrFail($id);

        // Get related properties (same city or same property type)
        $relatedProperties = PropertyListing::with(['primaryImage'])
            ->where('is_active', true)
            ->where('id', '!=', $property->id)
            ->where(function($query) use ($property) {
                $query->where('city', $property->city)
                      ->orWhere('property_type', $property->property_type);
            })
            ->where('country', $property->country)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        // Generate SEO data using SeoService
        $locale = app()->getLocale();
        $seo = $this->seoService->generatePropertySeo($property, $locale);
        
        // Add hreflang tags
        $hreflangTags = $this->seoService->generateHreflangTags($property);
        $seo->hreflang_tags = $hreflangTags;
        
        // Add OG locale tags
        $ogLocale = $this->seoService->generateOgLocaleTags($locale);
        $seo->og_locale = $ogLocale['locale'];
        $seo->og_alternate_locales = $ogLocale['alternate_locales'];

        return view('property-detail', compact('property', 'relatedProperties', 'seo'));
    }

    /**
     * Store a contact message for a property listing.
     */
    public function sendMessage(Request $request, $locale, $country, $city, $id)
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', __('messages.auth.login_required'));
        }

        // Validar los datos del formulario
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:2000',
        ]);

        // Buscar la propiedad
        $property = PropertyListing::with('user')->findOrFail($id);

        // Verificar que el usuario no esté contactando su propia propiedad
        if ($property->user_id === Auth::id()) {
            return back()->with('error', __('messages.property.cannot_contact_own'));
        }

        // Crear el mensaje
        $propertyMessage = PropertyMessage::create([
            'property_listing_id' => $property->id,
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'message' => $validated['message'],
        ]);

        // Enviar email al propietario
        try {
            Mail::to($property->user->email)->send(new PropertyMessageReceived($propertyMessage));
        } catch (\Exception $e) {
            \Log::error('Failed to send property message email: ' . $e->getMessage());
        }

        return back()->with('success', __('messages.property.message_sent'));
    }
}

