<?php

namespace App\Http\Controllers;

use App\Models\PropertyContact;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PropertyContactController extends Controller
{
    /**
     * Registra un click en WhatsApp o Teléfono desde la ficha de un anuncio
     * y crea automáticamente una solicitud para el visitante autenticado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:property_listings,id',
            'action'     => 'required|in:whatsapp,phone',
        ]);

        $listing = PropertyListing::with('user')->findOrFail($request->listing_id);

        // No registrar si el visitante es el dueño
        if ($listing->user_id === auth()->id()) {
            return response()->json(['ok' => true]);
        }

        PropertyContact::create([
            'listing_id'      => $listing->id,
            'visitor_user_id' => auth()->id(),
            'owner_user_id'   => $listing->user_id,
            'action'          => $request->action,
        ]);

        // Auto-crear solicitud para el visitante autenticado
        if (auth()->check()) {
            $this->createAutoRequest(auth()->user(), $listing, $request->action);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Crea automáticamente una PropertyRequest basada en el anuncio contactado,
     * evitando duplicados si ya existe una solicitud para el mismo anuncio.
     */
    protected function createAutoRequest(\App\Models\User $visitor, PropertyListing $listing, string $action): void
    {
        $source = $action === 'phone' ? 'phone_contact' : 'whatsapp_contact';

        $alreadyExists = PropertyRequest::where('user_id', $visitor->id)
            ->where('source_listing_id', $listing->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        try {
            PropertyRequest::create([
                'user_id'           => $visitor->id,
                'client_name'       => $visitor->name,
                'client_email'      => $visitor->email,
                'client_phone'      => $visitor->movil ?? null,
                'title'             => __('properties.auto_request_title', ['title' => $listing->title]),
                'description'       => __('properties.auto_request_description', ['title' => $listing->title, 'city' => $listing->city]),
                'property_type'     => $listing->property_type,
                'transaction_type'  => $listing->transaction_type,
                'max_budget'        => null,
                'currency'          => $listing->currency ?? 'USD',
                'city'              => $listing->city,
                'state'             => $listing->state,
                'country'           => $listing->country,
                'is_active'         => true,
                'source'            => $source,
                'source_listing_id' => $listing->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Auto-request creation failed after contact: ' . $e->getMessage(), [
                'listing_id' => $listing->id,
                'user_id'    => $visitor->id,
            ]);
        }
    }

    /**
     * Dashboard: lista de contactos recibidos en los anuncios del usuario.
     */
    public function index(Request $request)
    {
        $query = PropertyContact::with(['listing.primaryImage', 'listing.firstImage', 'visitor'])
            ->where('owner_user_id', auth()->id());

        if ($request->filled('listing_id')) {
            $query->where('listing_id', $request->listing_id);
        }

        $contacts = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        // Marcar todos los no vistos como vistos
        PropertyContact::where('owner_user_id', auth()->id())
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        $filterListing = $request->filled('listing_id')
            ? \App\Models\PropertyListing::find($request->listing_id)
            : null;

        return view('theme::pages.dashboard.contacts.index', compact('contacts', 'filterListing'));
    }

    /**
     * Guardar/actualizar notas sobre un contacto.
     */
    public function updateNotes(Request $request, PropertyContact $contact)
    {
        abort_if($contact->owner_user_id !== auth()->id(), 403);

        $request->validate(['notes' => 'nullable|string|max:1000']);

        $contact->update(['notes' => $request->notes]);

        return back()->with('success', 'Nota guardada.');
    }
}
