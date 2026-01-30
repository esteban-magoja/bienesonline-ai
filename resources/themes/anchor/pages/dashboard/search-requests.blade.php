<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

middleware('auth');
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
    public bool $canSearch = false;
    
    public function mount()
    {
        $user = auth()->user();
        
        // Verificar membresía premium
        $this->canSearch = $user->hasRole('admin') || $user->hasRole('premium');
        
        // Get unique countries from active property requests
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
        
        $startTime = microtime(true);
        
        // Validation
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
            
            // Generate embedding for search term
            $searchEmbedding = $this->generateEmbedding($this->searchTerm);
            
            if ($searchEmbedding) {
                // Convert array to PostgreSQL vector format
                $embeddingString = '[' . implode(',', $searchEmbedding) . ']';
                
                // Search by similarity using pgvector
                $this->requests = $query
                    ->selectRaw("*, (embedding <=> ?::vector) * -1 + 1 as similarity_raw", [$embeddingString])
                    ->whereNotNull('embedding')
                    ->orderByRaw("embedding <=> ?::vector", [$embeddingString])
                    ->limit(50)
                    ->get()
                    ->map(function ($request) {
                        $request->similarity = max(0, min(100, $request->similarity_raw * 100));
                        return $request;
                    })
                    ->filter(function ($request) {
                        return $request->similarity >= 20;
                    })
                    ->values();
                
                $this->totalResults = $this->requests->count();
            } else {
                $this->requests = $query->latest()->take(20)->get();
                $this->totalResults = $this->requests->count();
            }
            
            $this->searchTime = round((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            Log::error('Error in request search: ' . $e->getMessage());
            $this->validationErrors[] = __('dashboard.search_requests.validation.search_error');
            $this->requests = collect();
            $this->totalResults = 0;
        }
        
        $this->isSearching = false;
    }
    
    public function clearSearch()
    {
        $this->searchTerm = '';
        $this->selectedCountry = '';
        $this->requests = [];
        $this->totalResults = 0;
        $this->searchTime = 0;
        $this->validationErrors = [];
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

    public function grantPremiumRole(): void
    {
        $user = auth()->user();
        
        // Buscar el rol premium
        $premiumRole = \Spatie\Permission\Models\Role::where('name', 'premium')->first();
        
        if ($premiumRole && !$user->hasRole('premium')) {
            $user->assignRole('premium');
            $this->canSearch = true;
            session()->flash('success', __('dashboard.search_requests.premium_granted'));
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

        @if (!$canSearch)
            <div class="mt-6 p-8 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-yellow-800">
                            {{ __('dashboard.search_requests.premium_required') }}
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>{{ __('dashboard.search_requests.premium_description') }}</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>{{ __('dashboard.search_requests.premium_benefit_1') }}</li>
                                <li>{{ __('dashboard.search_requests.premium_benefit_2') }}</li>
                                <li>{{ __('dashboard.search_requests.premium_benefit_3') }}</li>
                                <li>{{ __('dashboard.search_requests.premium_benefit_4') }}</li>
                                <li>{{ __('dashboard.search_requests.premium_benefit_5') }}</li>
                            </ul>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <a href="{{ route('settings.subscription') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                {{ __('dashboard.search_requests.get_premium') }}
                            </a>
                            <button wire:click="grantPremiumRole" type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('dashboard.search_requests.already_premium') }}
                            </button>
                        </div>
                    </div>
                </div>
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
                                :placeholder="__('dashboard.search_requests.search_placeholder')" 
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
                    @foreach($requests as $propertyRequest)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow duration-200">
                            <!-- Header -->
                            <div class="mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                    {{ $propertyRequest->title }}
                                </h3>
                                
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
                            
                            <!-- Similarity Score -->
                            @if($searchTerm && isset($propertyRequest->similarity))
                                <div class="mb-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ __('dashboard.search_requests.relevance') }}:</span>
                                        <span class="font-medium text-green-600">{{ number_format($propertyRequest->similarity, 0) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $propertyRequest->similarity }}%"></div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Expiration Date -->
                            @if($propertyRequest->expires_at)
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ __('dashboard.search_requests.valid_until') }}: {{ $propertyRequest->expires_at->format('d/m/Y') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
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
        @endif
    </x-app.container>
    @endvolt
</x-layouts.app>
