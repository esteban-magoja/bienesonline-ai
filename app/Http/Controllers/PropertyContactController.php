<?php

namespace App\Http\Controllers;

use App\Models\PropertyContact;
use App\Models\PropertyListing;
use Illuminate\Http\Request;

class PropertyContactController extends Controller
{
    /**
     * Registra un click en WhatsApp o Teléfono desde la ficha de un anuncio.
     */
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:property_listings,id',
            'action'     => 'required|in:whatsapp,phone',
        ]);

        $listing = PropertyListing::findOrFail($request->listing_id);

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

        return response()->json(['ok' => true]);
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
