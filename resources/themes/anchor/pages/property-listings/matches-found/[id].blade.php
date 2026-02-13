<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyListing;
use App\Services\PropertyMatchingService;

middleware('auth');
name('property-listings.matches-found');

new class extends Component {
    public PropertyListing $listing;
    public $matches;
    public $matchCount = 0;

    public function mount($id)
    {
        $this->listing = PropertyListing::with('images')->findOrFail($id);
        
        // Verificar que el usuario es el propietario
        if ($this->listing->user_id !== auth()->id()) {
            abort(403);
        }

        // Buscar matches usando el servicio
        $matchingService = app(PropertyMatchingService::class);
        $minScore = config('matching.min_score', 70);
        
        $allMatches = $matchingService->findMatchesForListing($this->listing, 100);
        
        // Filtrar por score mínimo
        $this->matches = $allMatches->filter(function($match) use ($minScore) {
            return $match->match_score >= $minScore;
        });
        
        $this->matchCount = $this->matches->count();
    }

    public function viewMatches()
    {
        return $this->redirect(route('dashboard.matches.show', ['listing' => $this->listing->id]));
    }

    public function goToDashboard()
    {
        return $this->redirect(route('dashboard'));
    }

    public function viewListing()
    {
        $locale = app()->getLocale();
        $country = \Illuminate\Support\Str::slug($this->listing->country);
        $city = \Illuminate\Support\Str::slug($this->listing->city);
        $slug = \Illuminate\Support\Str::slug($this->listing->title);
        
        return $this->redirect("/{$locale}/{$country}/{$city}/propiedad/{$this->listing->id}-{$slug}");
    }
};
?>

<x-layouts.app>
    @volt('property-listings.matches-found')
    <x-app.container>
        <div class="max-w-4xl mx-auto">
            {{-- Header de éxito --}}
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-green-100 rounded-full dark:bg-green-900/20">
                    <svg class="w-10 h-10 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                    ¡Anuncio Publicado Exitosamente!
                </h1>
                <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                    Tu propiedad ya está disponible públicamente
                </p>
            </div>

            {{-- Card del anuncio publicado --}}
            <div class="p-6 mb-8 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-start gap-4">
                    @if($listing->primaryImage)
                        <img src="{{ $listing->primaryImage->image_url }}" 
                             alt="{{ $listing->title }}"
                             class="object-cover w-24 h-24 rounded-lg">
                    @endif
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $listing->title }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $listing->city }}, {{ $listing->state }}, {{ $listing->country }}
                        </p>
                        <div class="flex items-center gap-4 mt-3">
                            <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $listing->currency }} {{ number_format($listing->price, 0, ',', '.') }}
                            </span>
                            <span class="px-3 py-1 text-sm font-medium text-green-800 bg-green-100 rounded-full dark:bg-green-900/20 dark:text-green-400">
                                {{ __('properties.transaction_types.' . $listing->transaction_type) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-4">
                    <button wire:click="viewListing" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="inline w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Ver Anuncio Público
                    </button>
                </div>
            </div>

            {{-- Sección de matches --}}
            @if($matchCount > 0)
                <div class="p-8 mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg dark:from-blue-900/20 dark:to-indigo-900/20 dark:border-blue-700">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-full dark:bg-blue-900/40">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                ¡Encontramos {{ $matchCount }} {{ $matchCount === 1 ? 'Solicitud Interesada' : 'Solicitudes Interesadas' }}!
                            </h3>
                            <p class="mt-2 text-gray-700 dark:text-gray-300">
                                Hemos encontrado usuarios buscando propiedades como la tuya. Estas personas han publicado solicitudes que coinciden con las características de tu anuncio.
                            </p>
                            
                            {{-- Lista resumida de matches --}}
                            <div class="mt-4 space-y-3">
                                @foreach($matches->take(3) as $match)
                                    <div class="p-4 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ $match->title }}
                                                </h4>
                                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $match->city }}, {{ $match->state }} • 
                                                    Presupuesto: {{ $match->currency }} {{ number_format($match->min_budget, 0, ',', '.') }}
                                                    @if($match->max_budget)
                                                        - {{ number_format($match->max_budget, 0, ',', '.') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="flex flex-col items-end ml-4">
                                                <span class="px-3 py-1 text-sm font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">
                                                    {{ round($match->match_score) }}% match
                                                </span>
                                                @if($match->match_level === 'exact')
                                                    <span class="mt-1 text-xs text-green-600 dark:text-green-400">Exacto</span>
                                                @elseif($match->match_level === 'smart')
                                                    <span class="mt-1 text-xs text-blue-600 dark:text-blue-400">Inteligente</span>
                                                @else
                                                    <span class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">Flexible</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                
                                @if($matchCount > 3)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Y {{ $matchCount - 3 }} solicitud{{ $matchCount - 3 > 1 ? 'es' : '' }} más...
                                    </p>
                                @endif
                            </div>

                            <div class="flex gap-3 mt-6">
                                <button wire:click="viewMatches" class="inline-flex items-center px-6 py-3 text-base font-medium text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Ver Todos los Matches
                                </button>
                            </div>

                            <div class="p-4 mt-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-700">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-800 dark:text-blue-300">
                                            <strong>Consejo:</strong> Estos usuarios han sido notificados automáticamente sobre tu propiedad. También puedes contactarlos directamente desde la página de matches.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-8 mb-8 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 mb-4 bg-gray-200 rounded-full dark:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            No hay solicitudes compatibles por ahora
                        </h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            No te preocupes, cuando alguien publique una solicitud que coincida con tu propiedad, te notificaremos automáticamente.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Acciones finales --}}
            <div class="flex gap-4">
                <button wire:click="goToDashboard" class="flex-1 px-6 py-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                    Ir al Dashboard
                </button>
                <a href="{{ route('property-listings.create') }}" class="flex-1 px-6 py-3 text-base font-medium text-center text-indigo-600 bg-white border border-indigo-600 rounded-lg shadow-sm hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-indigo-400 dark:border-indigo-500 dark:hover:bg-gray-700">
                    Publicar Otro Anuncio
                </a>
            </div>
        </div>
    </x-app.container>
    @endvolt
</x-layouts.app>
