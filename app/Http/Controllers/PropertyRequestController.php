<?php

namespace App\Http\Controllers;

use App\Models\PropertyRequest;
use App\Services\PropertyMatchingService;
use App\Http\Requests\StorePropertyRequestRequest;
use App\Http\Requests\UpdatePropertyRequestRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PropertyRequestController extends Controller
{
    protected $matchingService;

    public function __construct(PropertyMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Display a listing of user's property requests.
     */
    public function index()
    {
        $requests = PropertyRequest::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('theme::pages.dashboard.requests.index', compact('requests'));
    }

    /**
     * Show the form for creating a new property request.
     */
    public function create()
    {
        $countries = \Nnjeim\World\Models\Country::all();
        $currencies = ['USD', 'ARS', 'EUR', 'BRL', 'MXN', 'CLP'];
        
        return view('theme::pages.dashboard.requests.create', compact('countries', 'currencies'));
    }

    /**
     * Store a newly created property request.
     */
    public function store(StorePropertyRequestRequest $request)
    {
        $validated = $request->validated();
        
        $validated['user_id'] = auth()->id();

        // Generar embedding usando el idioma del usuario
        $userLocale = session('locale', 'es');
        $title = $validated['title'][$userLocale];
        $description = $validated['description'][$userLocale];
        
        $embedding = $this->generateEmbedding($title, $description);
        if ($embedding) {
            $validated['embedding'] = $embedding;
        }

        // Convertir arrays a JSON para campos i18n
        $validated['title_i18n'] = json_encode($validated['title']);
        $validated['description_i18n'] = json_encode($validated['description']);
        
        // Mantener campos legacy para compatibilidad
        $validated['title'] = $title;
        $validated['description'] = $description;

        $propertyRequest = PropertyRequest::create($validated);

        return redirect()
            ->route('dashboard.requests.show', $propertyRequest)
            ->with('success', __('messages.request_created_successfully'));
    }

    /**
     * Display the specified property request with matches.
     */
    public function show(PropertyRequest $propertyRequest)
    {
        // Verificar que el usuario sea el dueño
        if ($propertyRequest->user_id !== auth()->id()) {
            abort(403);
        }

        // Obtener matches
        $matches = $this->matchingService->findMatchesForRequest($propertyRequest, 20);

        return view('theme::pages.dashboard.requests.show', compact('propertyRequest', 'matches'));
    }

    /**
     * Show the form for editing the specified property request.
     */
    public function edit(PropertyRequest $propertyRequest)
    {
        // Verificar que el usuario sea el dueño
        if ($propertyRequest->user_id !== auth()->id()) {
            abort(403);
        }

        $countries = \Nnjeim\World\Models\Country::all();
        $currencies = ['USD', 'ARS', 'EUR', 'BRL', 'MXN', 'CLP'];

        return view('theme::pages.dashboard.requests.edit', compact('propertyRequest', 'countries', 'currencies'));
    }

    /**
     * Update the specified property request.
     */
    public function update(UpdatePropertyRequestRequest $request, PropertyRequest $propertyRequest)
    {
        $validated = $request->validated();

        // Obtener idioma del usuario
        $userLocale = session('locale', 'es');
        $title = $validated['title'][$userLocale];
        $description = $validated['description'][$userLocale];

        // Regenerar embedding si cambió el contenido
        $oldTitle = $propertyRequest->getTranslation('title', $userLocale);
        $oldDescription = $propertyRequest->getTranslation('description', $userLocale);
        
        if ($oldTitle !== $title || $oldDescription !== $description) {
            $embedding = $this->generateEmbedding($title, $description);
            if ($embedding) {
                $validated['embedding'] = $embedding;
            }
        }

        // Convertir arrays a JSON
        $validated['title_i18n'] = json_encode($validated['title']);
        $validated['description_i18n'] = json_encode($validated['description']);
        
        // Mantener campos legacy
        $validated['title'] = $title;
        $validated['description'] = $description;

        $propertyRequest->update($validated);

        return redirect()
            ->route('dashboard.requests.show', $propertyRequest)
            ->with('success', __('messages.request_updated_successfully'));
    }

    /**
     * Remove the specified property request.
     */
    public function destroy(PropertyRequest $propertyRequest)
    {
        // Verificar que el usuario sea el dueño
        if ($propertyRequest->user_id !== auth()->id()) {
            abort(403);
        }

        $propertyRequest->delete();

        return redirect()
            ->route('dashboard.requests.index')
            ->with('success', __('messages.request_deleted_successfully'));
    }

    /**
     * Toggle active status of the property request.
     */
    public function toggleActive(PropertyRequest $propertyRequest)
    {
        // Verificar que el usuario sea el dueño
        if ($propertyRequest->user_id !== auth()->id()) {
            abort(403);
        }

        $propertyRequest->update([
            'is_active' => !$propertyRequest->is_active
        ]);

        $message = $propertyRequest->is_active 
            ? __('messages.request_activated') 
            : __('messages.request_deactivated');

        return back()->with('success', $message);
    }

    /**
     * Generate embedding using OpenAI API.
     *
     * @param string $title
     * @param string $description
     * @return array|null
     */
    protected function generateEmbedding(string $title, string $description): ?array
    {
        try {
            $text = $title . ' ' . $description;
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => 'text-embedding-ada-002',
            ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }

            \Log::error('Error generating embedding: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            \Log::error('Exception generating embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get states for a country (AJAX)
     */
    public function getStates(Request $request)
    {
        try {
            $countryId = $request->country_id;
            
            if (!$countryId) {
                return response()->json(['error' => 'country_id is required'], 400);
            }
            
            $states = \Nnjeim\World\Models\State::where('country_id', $countryId)
                ->orderBy('name')
                ->get(['id', 'name', 'country_id'])
                ->toArray();
            
            return response()->json($states, 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error getting states: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading states'], 500);
        }
    }

    /**
     * Get cities for a state (AJAX)
     */
    public function getCities(Request $request)
    {
        try {
            $stateId = $request->state_id;
            
            if (!$stateId) {
                return response()->json(['error' => 'state_id is required'], 400);
            }
            
            $cities = \Nnjeim\World\Models\City::where('state_id', $stateId)
                ->orderBy('name')
                ->get(['id', 'name', 'state_id'])
                ->toArray();
            
            return response()->json($cities, 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error getting cities: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading cities'], 500);
        }
    }
}
