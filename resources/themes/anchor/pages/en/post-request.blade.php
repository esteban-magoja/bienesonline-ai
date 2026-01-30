<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\PropertyRequest;
use Livewire\Attributes\Rule;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;
use Livewire\Attributes\Computed;

name('requests.create');

// Locale establecido por middleware SetLocale basado en URL /en/...

new class extends Component {
    
    #[Rule('required|string|max:255')]
    public string $client_name = '';

    #[Rule('required|email|max:255')]
    public string $client_email = '';

    #[Rule('required|string|max:20')]
    public string $client_phone = '';

    #[Rule('required|string|max:255')]
    public string $title = '';

    #[Rule('required|string|min:20')]
    public string $description = '';

    #[Rule('required|string')]
    public string $property_type = 'casa';

    #[Rule('required|string')]
    public string $transaction_type = 'venta';

    #[Rule('nullable|numeric|min:0')]
    public ?string $min_budget = '';

    #[Rule('required|numeric|min:0')]
    public string $max_budget = '';

    #[Rule('required|string|max:3')]
    public string $currency = 'USD';

    #[Rule('nullable|integer|min:0')]
    public ?int $min_bedrooms = null;

    #[Rule('nullable|integer|min:0')]
    public ?int $min_bathrooms = null;

    #[Rule('nullable|integer|min:0')]
    public ?int $min_parking_spaces = null;

    #[Rule('nullable|integer|min:0')]
    public ?int $min_area = null;

    #[Rule('nullable|string|max:255')]
    public ?string $city = '';

    #[Rule('required')]
    public $selectedCountry = null;

    #[Rule('nullable')]
    public $selectedState = null;

    #[Rule('nullable|date|after:today')]
    public $expires_at = null;

    public $countries;
    public $states = [];
    public $cities = [];
    public $availableCurrencies = ['USD', 'ARS', 'EUR', 'BRL', 'MXN', 'CLP', 'COP'];

    #[Computed]
    public function country()
    {
        return Country::find($this->selectedCountry)?->name;
    }

    #[Computed]
    public function state()
    {
        return State::find($this->selectedState)?->name;
    }

    public function mount()
    {
        if (auth()->check()) {
            $this->countries = Country::all();
            
            // Precargar datos del usuario logueado
            $user = auth()->user();
            $this->client_name = $user->name ?? '';
            $this->client_email = $user->email ?? '';
            $this->client_phone = $user->movil ?? '';
        }
    }

    public function updatedSelectedCountry($countryId)
    {
        $this->states = State::where('country_id', $countryId)->orderBy('name')->get();
        $this->selectedState = null;
        $this->cities = [];
        $this->city = '';

        // Actualizar moneda según el país
        $country = Country::find($countryId);
        if ($country && isset($country->currency['code'])) {
            $this->currency = $country->currency['code'];
        }
    }

    public function updatedSelectedState($stateId)
    {
        if ($stateId) {
            $this->cities = City::where('state_id', $stateId)->orderBy('name')->get();
        } else {
            $this->cities = [];
        }
        $this->city = '';
    }

    public function save(): void
    {
        $validated = $this->validate();
        
        $validated['user_id'] = auth()->id();
        $validated['country'] = $this->country;
        $validated['state'] = $this->state;

        // Generar embedding
        $embedding = $this->generateEmbedding($validated['title'], $validated['description']);
        if ($embedding) {
            $validated['embedding'] = $embedding;
        }

        $propertyRequest = PropertyRequest::create($validated);

        session()->flash('success', 'Tu solicitud ha sido creada exitosamente.');
        
        $this->redirectRoute('dashboard.requests.show', $propertyRequest);
    }

    protected function generateEmbedding(string $title, string $description): ?array
    {
        try {
            $text = $title . ' ' . $description;
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => 'text-embedding-ada-002',
            ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Exception generating embedding: ' . $e->getMessage());
            return null;
        }
    }
};
?>

<x-layouts.marketing>
    
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-r from-gray-100 to-gray-200 py-10">
        <div class="absolute inset-0 bg-white/10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                    {{ __('dashboard.post_request.hero_title') }}
                </h1>
                <p class="text-xl text-gray-700 mb-4 max-w-3xl mx-auto">
                    {{ __('dashboard.post_request.hero_subtitle') }}
                </p>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">
                    {{ __('dashboard.post_request.hero_desc') }}
                </p>
            </div>
        </div>
    </section>

    @guest
        <!-- Not Logged In Message -->
        <section class="py-16">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow-xl p-8 md:p-12 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-6">
                        <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">
                        {{ __('dashboard.post_request.login_title') }}
                    </h2>
                    <p class="text-lg text-gray-600 mb-8">
                        {{ __('dashboard.post_request.login_desc') }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            {{ __('dashboard.post_request.login_button') }}
                        </a>
                        <a href="{{ route('signup') }}" class="inline-flex items-center justify-center px-8 py-3 border-2 border-blue-600 text-base font-medium rounded-md text-blue-600 bg-white hover:bg-blue-50 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                            {{ __('dashboard.post_request.signup_button') }}
                        </a>
                    </div>
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.post_request.why_create') }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                            <div class="flex items-start">
                                <svg class="h-6 w-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ __('dashboard.post_request.feature_auto_search') }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ __('dashboard.post_request.feature_auto_search_desc') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="h-6 w-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ __('dashboard.post_request.feature_notifications') }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ __('dashboard.post_request.feature_notifications_desc') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="h-6 w-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ __('dashboard.post_request.feature_ai') }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ __('dashboard.post_request.feature_ai_desc') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @else
        <!-- Request Form (Logged In Users) -->
        @volt('public-request-create')
        <section class="py-16">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                @if (session('success'))
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 text-green-800 rounded-r-md">
                        <div class="flex">
                            <svg class="h-5 w-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <p>{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="save" class="space-y-8">
                    
                    <!-- Datos del Cliente -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ __('dashboard.post_request.section_contact') }}
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">{{ __('dashboard.post_request.section_contact_desc') }}</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="client_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.client_name') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       wire:model="client_name" 
                                       id="client_name" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="{{ __('dashboard.request_form.client_name_placeholder') }}"
                                       required>
                                @error('client_name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="client_email" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.client_email') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="email" 
                                       wire:model="client_email" 
                                       id="client_email" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="{{ __('dashboard.request_form.client_email_placeholder') }}"
                                       required>
                                @error('client_email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="client_phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.client_phone') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       wire:model="client_phone" 
                                       id="client_phone" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="{{ __('dashboard.request_form.client_phone_placeholder') }}"
                                       required>
                                <p class="mt-2 text-sm text-gray-500">{{ __('dashboard.request_form.phone_format') }}</p>
                                @error('client_phone') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Información Básica -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ __('dashboard.post_request.section_basic') }}
                        </h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.title_label') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       wire:model="title" 
                                       id="title" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="{{ __('dashboard.request_form.title_placeholder') }}">
                                @error('title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.description_label') }} <span class="text-red-500">*</span>
                                </label>
                                <textarea wire:model="description" 
                                          id="description" 
                                          rows="5"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                          placeholder="{{ __('dashboard.request_form.description_placeholder') }}"></textarea>
                                <p class="mt-2 text-sm text-gray-500">{{ __('dashboard.request_form.description_hint') }}</p>
                                @error('description') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="property_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('dashboard.request_form.property_type') }} <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="property_type" 
                                            id="property_type" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                        <option value="casa">{{ __('properties.types.house') }}</option>
                                        <option value="departamento">{{ __('properties.types.apartment') }}</option>
                                        <option value="local">{{ __('properties.types.commercial') }}</option>
                                        <option value="oficina">{{ __('properties.types.office') }}</option>
                                        <option value="terreno">{{ __('properties.types.land') }}</option>
                                        <option value="campo">{{ __('properties.types.field') }}</option>
                                        <option value="galpon">{{ __('properties.types.warehouse') }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="transaction_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('dashboard.request_form.transaction_type') }} <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="transaction_type" 
                                            id="transaction_type" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                        <option value="venta">{{ __('properties.transaction_types.sale') }}</option>
                                        <option value="alquiler">{{ __('properties.transaction_types.rent') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ __('dashboard.post_request.section_location') }}
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="country" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.country') }} <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="selectedCountry" 
                                        id="country" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                    <option value="">{{ __('dashboard.request_form.select_country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedCountry') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="state" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.state') }}
                                </label>
                                <select wire:model.live="selectedState" 
                                        id="state" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                        {{ empty($states) ? 'disabled' : '' }}>
                                    <option value="">{{ __('dashboard.request_form.select_state') }}</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="city" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.city') }}
                                </label>
                                <select wire:model="city" 
                                        id="city" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                        {{ empty($cities) ? 'disabled' : '' }}>
                                    <option value="">{{ __('dashboard.request_form.select_city') }}</option>
                                    @foreach($cities as $cityItem)
                                        <option value="{{ $cityItem->name }}">{{ $cityItem->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Presupuesto -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ __('dashboard.post_request.section_budget') }}
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="currency" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.currency') }} <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="currency" 
                                        id="currency" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                    @foreach($availableCurrencies as $curr)
                                        <option value="{{ $curr }}">{{ $curr }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="min_budget" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.min_budget') }}
                                </label>
                                <input type="number" 
                                       wire:model="min_budget" 
                                       id="min_budget" 
                                       step="0.01"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="0">
                                @error('min_budget') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="max_budget" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('dashboard.request_form.max_budget') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       wire:model="max_budget" 
                                       id="max_budget" 
                                       step="0.01"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       placeholder="250000">
                                @error('max_budget') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>


                    <!-- Características Mínimas -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            {{ __('dashboard.post_request.section_features') }}
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">{{ __('dashboard.post_request.section_features_desc') }}</p>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div>
                                <label for="min_bedrooms" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('properties.features.bedrooms') }}
                                </label>
                                <input type="number" 
                                       wire:model="min_bedrooms" 
                                       id="min_bedrooms" 
                                       min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            </div>

                            <div>
                                <label for="min_bathrooms" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('properties.features.bathrooms') }}
                                </label>
                                <input type="number" 
                                       wire:model="min_bathrooms" 
                                       id="min_bathrooms" 
                                       min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            </div>

                            <div>
                                <label for="min_parking_spaces" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('properties.features.garages') }}
                                </label>
                                <input type="number" 
                                       wire:model="min_parking_spaces" 
                                       id="min_parking_spaces" 
                                       min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            </div>

                            <div>
                                <label for="min_area" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('properties.features.area') }}
                                </label>
                                <input type="number" 
                                       wire:model="min_area" 
                                       id="min_area" 
                                       min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            </div>
                        </div>
                    </div>

                    <!-- Fecha de Expiración -->
                    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            {{ __('dashboard.post_request.section_expiration') }}
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">{{ __('dashboard.post_request.section_expiration_desc') }}</p>
                        
                        <div class="max-w-md">
                            <label for="expires_at" class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ __('dashboard.request_form.expiration') }}
                            </label>
                            <input type="date" 
                                   wire:model="expires_at" 
                                   id="expires_at" 
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            <p class="mt-2 text-sm text-gray-500">{{ __('dashboard.request_form.expiration_hint') }}</p>
                            @error('expires_at') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Información sobre el proceso -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 rounded-r-md p-6">
                        <div class="flex">
                            <svg class="h-6 w-6 text-blue-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h4 class="text-lg font-semibold text-blue-900 mb-2">{{ __('dashboard.post_request.info_title') }}</h4>
                                <ul class="text-sm text-blue-800 space-y-2">
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ __('dashboard.post_request.info_item1') }}</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ __('dashboard.post_request.info_item2') }}</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ __('dashboard.post_request.info_item3') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center pt-6">
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 disabled:opacity-75 disabled:cursor-wait shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span wire:loading.remove wire:target="save">{{ __('dashboard.request_form.publish_button') }}</span>
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('dashboard.request_form.saving') }}
                            </span>
                        </button>
                        <a href="{{ route_localized('home') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            {{ __('dashboard.request_form.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </section>
        @endvolt
    @endguest

</x-layouts.marketing>
