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
        $minScore = config('matching.min_score', 50);
        
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
                    @php $displayImage = $listing->primaryImage ?? $listing->firstImage; @endphp
                    @if($displayImage)
                        <img src="{{ $displayImage->image_url }}" 
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
                                {{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}
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
                        <div class="flex-shrink-0 hidden sm:block">
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
                            @php
                                $locale = app()->getLocale();
                                $listingUrl = url("/{$locale}/" . \Illuminate\Support\Str::slug($listing->country) . "/" . \Illuminate\Support\Str::slug($listing->city) . "/propiedad/{$listing->id}-" . \Illuminate\Support\Str::slug($listing->title));
                            @endphp
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

                                        {{-- Datos de contacto del solicitante --}}
                                        @if($match->client_name || $match->client_email || $match->client_phone)
                                            <div class="flex flex-col gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                                @if($match->client_name)
                                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $match->client_name }}</p>
                                                @endif
                                                <div class="flex gap-2">
                                                    @if($match->client_email)
                                                        <a href="mailto:{{ $match->client_email }}?subject={{ rawurlencode('Propiedad que coincide con tu búsqueda') }}&body={{ rawurlencode("Hola {$match->client_name},\n\nTengo una propiedad que podría interesarte:\n{$listingUrl}") }}"
                                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                            </svg>
                                                            Email
                                                        </a>
                                                    @endif
                                                    @if($match->client_phone)
                                                        @php $phone = preg_replace('/[^0-9]/', '', $match->client_phone); @endphp
                                                        <a href="https://wa.me/{{ $phone }}?text={{ rawurlencode("Hola {$match->client_name}, tengo una propiedad que coincide con tu búsqueda:\n{$listingUrl}") }}"
                                                           target="_blank"
                                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                            </svg>
                                                            WhatsApp
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
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
    @if($matchCount > 0)
    <x-slot name="javascript">
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
        <script>
            confetti({
                particleCount: 150,
                spread: 80,
                origin: { y: 0.5 }
            });
            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.2, y: 0.6 } }), 400);
            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.8, y: 0.6 } }), 700);
        </script>
    </x-slot>
    @endif
    @endvolt
</x-layouts.app>
