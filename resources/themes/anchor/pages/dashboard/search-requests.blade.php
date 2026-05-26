<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

middleware(['auth']);
name('dashboard.search-requests');

new class extends Component {
    
    public string $searchTerm = '';
    public string $selectedCountry = '';
    public $countries = [];
    public $requests = [];
    public $totalResults = 0;
    public $searchTime = 0;
    public bool $isSearching = false;
    public $validationErrors = [];
    public int $currentPage = 1;
    public int $totalPages = 0;

    const PER_PAGE = 20;
    
    public function mount()
    {
        $this->countries = PropertyRequest::where('is_active', true)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values()
            ->toArray();
    }
    
    public function search()
    {
        $this->validationErrors = [];
        $this->isSearching = true;
        $this->currentPage = 1;
        
        $startTime = microtime(true);
        
        if (empty($this->selectedCountry)) {
            $this->validationErrors[] = __('dashboard.search_requests.validation.country_required');
        }
        
        if (empty($this->searchTerm)) {
            $this->validationErrors[] = __('dashboard.search_requests.validation.search_required');
        } elseif (strlen($this->searchTerm) < 5) {
            $this->validationErrors[] = __('dashboard.search_requests.validation.search_min_length');
        }
        
        if (!empty($this->validationErrors)) {
            $this->isSearching = false;
            return;
        }
        
        try {
            $query = PropertyRequest::where('is_active', true)
                ->where('user_id', '!=', auth()->id())
                ->where('country', $this->selectedCountry);
            
            $searchEmbedding = $this->generateEmbedding($this->searchTerm);
            
            if ($searchEmbedding) {
                $embeddingString = '[' . implode(',', $searchEmbedding) . ']';
                
                $this->requests = $query
                    ->with('user:id,name,email,movil')
                    ->selectRaw("id, user_id, title, description, property_type, transaction_type, min_budget, max_budget, currency, min_bedrooms, min_bathrooms, min_parking_spaces, min_area, city, state, country, is_active, expires_at, created_at, client_name, client_email, client_phone, (embedding <=> ?::vector) * -1 + 1 as similarity_raw", [$embeddingString])
                    ->whereNotNull('embedding')
                    ->orderByRaw("embedding <=> ?::vector", [$embeddingString])
                    ->limit(200)
                    ->get()
                    ->map(function ($request) {
                        $request->setAttribute('similarity', max(0, min(100, $request->similarity_raw * 100)));
                        $request->makeHidden(['similarity_raw']);
                        return $request;
                    })
                    ->filter(function ($request) {
                        return $request->similarity >= 20;
                    })
                    ->values();
                
                $this->totalResults = $this->requests->count();
            } else {
                $this->requests = $query->latest()->get();
                $this->totalResults = $this->requests->count();
            }

            $this->totalPages = (int) ceil($this->totalResults / self::PER_PAGE);
            $this->searchTime = round((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            Log::error('Error in request search: ' . $e->getMessage());
            $this->validationErrors[] = __('dashboard.search_requests.validation.search_error');
            $this->requests = collect();
            $this->totalResults = 0;
            $this->totalPages = 0;
        }
        
        $this->isSearching = false;
    }

    public function nextPage(): void
    {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
        }
    }

    public function prevPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= $this->totalPages) {
            $this->currentPage = $page;
        }
    }
    
    public function clearSearch()
    {
        $this->searchTerm = '';
        $this->selectedCountry = '';
        $this->requests = [];
        $this->totalResults = 0;
        $this->searchTime = 0;
        $this->validationErrors = [];
        $this->currentPage = 1;
        $this->totalPages = 0;
    }
    
    private function generateEmbedding(string $text): ?array
    {
        try {
            $apiKey = config('services.openai.api_key');
            
            if (empty($apiKey)) {
                Log::warning('OpenAI API key not configured');
                return null;
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);
            
            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }
            
            Log::error('OpenAI API error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Exception generating embedding: ' . $e->getMessage());
            return null;
        }
    }
};
?>

<x-layouts.app>
    @volt('dashboard-requests-search')
    <x-app.container>
        
        <x-app.heading
            title="{{ __('dashboard.search_requests.title') }}"
            description="{{ __('dashboard.search_requests.description') }}"
            :border="false"
        />

        {{-- Mensajes flash --}}
        @if (session()->has('success'))
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            {{ session('success') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">
                            {{ session('error') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Premium gate --}}
        @if(!auth()->user()->hasRole('premium'))
            <div class="mt-6 bg-amber-50 border border-amber-300 rounded-xl p-8 text-center shadow-sm">
                <svg class="w-14 h-14 mx-auto text-amber-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <h3 class="text-xl font-bold text-amber-800 mb-2">
                    {{ __('dashboard.search_requests.premium_required') }}
                </h3>
                <p class="text-amber-700 mb-6 max-w-md mx-auto">
                    {{ __('dashboard.search_requests.premium_description') }}
                </p>
                <a href="/settings/subscription"
                   class="inline-flex items-center px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition-colors duration-150 shadow-sm">
                    {{ __('dashboard.search_requests.get_premium') }}
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            </div>
        @else

        <!-- Search Form -->
        <div class="mt-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <form wire:submit.prevent="search" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Country Selection -->
                        <div class="md:col-span-1">
                            <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('dashboard.search_requests.country') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                wire:model="selectedCountry" 
                                id="country" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                                <option value="">{{ __('dashboard.search_requests.select_country') }}</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country }}">{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('dashboard.search_requests.search_label') }} <span class="text-red-500">*</span>
                                <span class="text-gray-500 text-xs">({{ __('dashboard.search_requests.min_5_chars') }})</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="searchTerm"
                                id="search"
                                placeholder="{{ __('dashboard.search_requests.search_placeholder') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required
                                minlength="5"
                            >
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-150 disabled:opacity-75 disabled:cursor-wait"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="search">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                {{ __('dashboard.search_requests.search_button') }}
                            </span>
                            <span wire:loading wire:target="search">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('dashboard.search_requests.searching') }}
                            </span>
                        </button>
                        
                        @if($searchTerm || $selectedCountry)
                            <button 
                                type="button"
                                wire:click="clearSearch"
                                class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                {{ __('dashboard.search_requests.clear') }}
                            </button>
                        @endif
                    </div>
                </form>
                
                <!-- Validation Errors -->
                @if(!empty($validationErrors))
                    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">{{ __('dashboard.search_requests.validation.check_fields') }}</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach($validationErrors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Results Section -->
        @if($totalResults > 0)
            <div class="mt-6">
                <!-- Results Header -->
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-gray-900">
                        {{ __('dashboard.search_requests.results_title') }}
                    </h2>
                    <p class="text-gray-600 text-sm">
                        {{ number_format($totalResults) }} {{ $totalResults === 1 ? __('dashboard.search_requests.request_found') : __('dashboard.search_requests.requests_found') }}
                        @if($selectedCountry)
                            {{ __('dashboard.search_requests.in_country', ['country' => $selectedCountry]) }}
                        @endif
                        @if($searchTerm)
                            {{ __('dashboard.search_requests.for_term', ['term' => $searchTerm]) }}
                        @endif
                        @if($searchTime > 0)
                            <span class="text-gray-500">
                                • {{ $searchTime }}ms
                                <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    IA
                                </span>
                            </span>
                        @endif
                    </p>
                </div>

                <!-- Results Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach(collect($requests)->forPage($currentPage, 20) as $propertyRequest)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow duration-200">
                            <!-- Header -->
                            <div class="mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 line-clamp-2 mb-1">
                                    {{ $propertyRequest->title }}
                                </h3>

                                @if($propertyRequest->similarity)
                                    @php $sim = (int) $propertyRequest->similarity; @endphp
                                    <div class="mb-2">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold
                                            {{ $sim >= 70 ? 'bg-green-100 text-green-800' : ($sim >= 45 ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800') }}">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            {{ $sim }}% similitud
                                        </span>
                                    </div>
                                @endif

                                <!-- Badges -->
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-blue-600 rounded">
                                        {{ ucfirst($propertyRequest->transaction_type) }}
                                    </span>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-purple-600 rounded">
                                        {{ ucfirst($propertyRequest->property_type) }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                                {{ $propertyRequest->description }}
                            </p>
                            
                            <!-- Location -->
                            <p class="text-sm text-gray-600 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                                {{ $propertyRequest->city }}, {{ $propertyRequest->state }}, {{ $propertyRequest->country }}
                            </p>
                            
                            <!-- Budget -->
                            <div class="mb-3">
                                <span class="text-lg font-bold text-green-600">
                                    {{ $propertyRequest->currency }} 
                                    @if($propertyRequest->min_budget)
                                        {{ number_format($propertyRequest->min_budget) }} - 
                                    @endif
                                    {{ number_format($propertyRequest->max_budget) }}
                                </span>
                            </div>
                            
                            <!-- Requirements -->
                            @if($propertyRequest->min_bedrooms || $propertyRequest->min_bathrooms || $propertyRequest->min_area)
                                <div class="grid grid-cols-3 gap-2 text-sm text-gray-600 mb-3">
                                    @if($propertyRequest->min_bedrooms)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                            </svg>
                                            {{ $propertyRequest->min_bedrooms }}+
                                        </div>
                                    @endif
                                    @if($propertyRequest->min_bathrooms)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $propertyRequest->min_bathrooms }}+
                                        </div>
                                    @endif
                                    @if($propertyRequest->min_area)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
                                            </svg>
                                            {{ number_format($propertyRequest->min_area) }}+ m²
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            <!-- Expiration Date -->
                            @if($propertyRequest->expires_at)
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ __('dashboard.search_requests.valid_until') }}: {{ $propertyRequest->expires_at->format('d/m/Y') }}
                                </p>
                            @endif

                            <!-- Contact Info -->
                            @php
                                $contactName  = $propertyRequest->client_name  ?: ($propertyRequest->user->name  ?? null);
                                $contactEmail = $propertyRequest->client_email ?: ($propertyRequest->user->email ?? null);
                                $contactPhone = $propertyRequest->client_phone ?: ($propertyRequest->user->movil ?? null);
                            @endphp
                            @if($contactName || $contactEmail || $contactPhone)
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-3">
                                    <div class="flex items-center gap-1 mb-2">
                                        <svg class="w-4 h-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <span class="text-xs font-semibold text-blue-900">{{ __('dashboard.requests.client') }}</span>
                                    </div>
                                    <div class="space-y-1 text-sm">
                                        @if($contactName)
                                            <div class="flex items-center gap-1">
                                                <span class="text-blue-700">👤</span>
                                                <span class="text-blue-900 font-medium">{{ $contactName }}</span>
                                            </div>
                                        @endif
                                        @if($contactEmail)
                                            <div class="flex items-center gap-1">
                                                <span class="text-blue-700">✉️</span>
                                                <a href="mailto:{{ $contactEmail }}" class="text-blue-900 hover:underline truncate">
                                                    {{ $contactEmail }}
                                                </a>
                                            </div>
                                        @endif
                                        @if($contactPhone)
                                            <div class="flex items-center gap-1">
                                                <span class="text-blue-700">📱</span>
                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contactPhone) }}"
                                                   target="_blank"
                                                   class="text-blue-900 hover:underline">
                                                    {{ $contactPhone }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Pagination Controls -->
                @if($totalPages > 1)
                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-sm text-gray-600">
                            {{ __('dashboard.search_requests.page_info', [
                                'from' => ($currentPage - 1) * 20 + 1,
                                'to'   => min($currentPage * 20, $totalResults),
                                'total' => $totalResults,
                            ]) }}
                        </p>
                        <div class="flex items-center gap-1">
                            <button wire:click="prevPage"
                                    @if($currentPage <= 1) disabled @endif
                                    class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                ← {{ __('dashboard.search_requests.prev') }}
                            </button>

                            @for($p = 1; $p <= $totalPages; $p++)
                                @if($p <= 2 || $p >= $totalPages - 1 || abs($p - $currentPage) <= 1)
                                    <button wire:click="goToPage({{ $p }})"
                                            class="px-3 py-2 text-sm font-medium rounded-lg border transition-colors
                                                {{ $currentPage === $p
                                                    ? 'bg-blue-600 border-blue-600 text-white'
                                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                                        {{ $p }}
                                    </button>
                                @elseif($p === 3 && $currentPage > 4)
                                    <span class="px-2 text-gray-400">…</span>
                                @elseif($p === $totalPages - 2 && $currentPage < $totalPages - 3)
                                    <span class="px-2 text-gray-400">…</span>
                                @endif
                            @endfor

                            <button wire:click="nextPage"
                                    @if($currentPage >= $totalPages) disabled @endif
                                    class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                {{ __('dashboard.search_requests.next') }} →
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @elseif($searchTerm && !$isSearching && empty($validationErrors))
            <!-- No Results -->
            <div class="mt-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('dashboard.search_requests.no_results') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('dashboard.search_requests.no_results_desc') }}
                    </p>
                </div>
            </div>
        @endif
        @endif {{-- premium gate --}}
    </x-app.container>
    @endvolt
</x-layouts.app>
