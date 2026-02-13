<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyListing;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Storage;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Currency;
use Livewire\Attributes\Computed;

middleware('auth');
name('property-listings.create');

new class extends Component {
    use WithFileUploads;

    public int $step = 1;
    public bool $canPublish = false;

    #[Rule('required|string|max:255')]
    public string $title = '';

    #[Rule('required|string')]
    public string $description = '';

    #[Rule('required|string')]
    public string $property_type = 'casa';

    #[Rule('required|string')]
    public string $transaction_type = 'venta';

    #[Rule('required|numeric|min:0')]
    public string $price = '';

    #[Rule('required|integer|min:0')]
    public int $bedrooms = 0;

    #[Rule('required|integer|min:0')]
    public int $bathrooms = 0;

    #[Rule('integer|min:0')]
    public int $parking_spaces = 0;

    #[Rule('required|numeric|min:0')]
    public int $area = 0;

    #[Rule('nullable|string')]
    public string $conditions = '';

    #[Rule('nullable|string|max:3')]
    public string $currency = 'USD';

    #[Rule('nullable|integer|min:0')]
    public ?int $lotsize = 0;

    #[Rule('nullable|string|max:255')]
    public string $address = '';

    #[Rule('required|string|max:255')]
    public string $city = '';

    #[Rule('required')]
    public $selectedCountry = null;

    #[Rule('required')]
    public $selectedState = null;

    #[Rule('nullable|numeric')]
    public ?float $latitude = null;

    #[Rule('nullable|numeric')]
    public ?float $longitude = null;

    public ?PropertyListing $propertyListing = null;

    public array $images = [];
    public array $imageUploads = [];
    public ?int $primaryImageIndex = null;

    public $countries;
    public $states = [];
    public $cities = [];
    public $currencies;
    public $availableCurrencies = [];

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
        $user = auth()->user();
        
        // Verificar que el usuario haya aceptado los términos
        if (!$user->hasAcceptedTerms()) {
            session()->flash('error', 'Debes aceptar los términos y condiciones antes de publicar anuncios.');
            $this->redirect(route('dashboard.terms'));
            return;
        }
        
        $this->canPublish = $user->hasRole('admin') || $user->hasRole('premium');
        
        $this->countries = Country::all();
        $this->currencies = Currency::all();
        $this->availableCurrencies = ['USD'];
    }

    public function updatedSelectedCountry($countryId)
    {
        $this->states = State::where('country_id', $countryId)->get();
        $this->selectedState = null;

        $country = Country::find($countryId);
        if ($country && isset($country->currency['code'])) {
            $this->currency = $country->currency['code'];
            $this->availableCurrencies = array_unique([$country->currency['code'], 'USD']);
        }

        if ($countryId) {
            $this->dispatch('country-selected-for-map');
        }
    }

    public function updatedSelectedState($stateId)
    {
        $this->cities = City::where('state_id', $stateId)->get();
    }

    public function with(): array
    {
        return [
            'countries' => Country::all(),
        ];
    }

    public function save(): void
    {
        // Verificar que el usuario haya aceptado los términos
        if (!auth()->user()->hasAcceptedTerms()) {
            session()->flash('error', 'Debes aceptar los términos y condiciones antes de publicar anuncios.');
            $this->redirect(route('dashboard.terms'));
            return;
        }
        
        if (!$this->canPublish) {
            $this->redirect(route('settings.subscription'));
            return;
        }
        
        $validated = $this->validate();
        $validated['user_id'] = auth()->id();
        $validated['country'] = $this->country;
        $validated['state'] = $this->state;
        $validated['latitude'] = $this->latitude;
        $validated['longitude'] = $this->longitude;

        $this->propertyListing = PropertyListing::create($validated);

        $this->step = 2;
    }

    public function updatedImageUploads()
    {
        $this->validate(['imageUploads.*' => 'image|max:10240']);
        $this->images = array_merge($this->images, $this->imageUploads);
        if (is_null($this->primaryImageIndex) && count($this->images) > 0) {
            $this->primaryImageIndex = 0;
        }
        $this->imageUploads = [];

        $this->dispatch('upload-finished');
    }

    public function removeImage($index)
    {
        array_splice($this->images, $index, 1);
        if (count($this->images) == 0) {
            $this->primaryImageIndex = null;
        }
        elseif ($this->primaryImageIndex == $index) {
            $this->primaryImageIndex = 0;
        }
        elseif ($this->primaryImageIndex > $index) {
            $this->primaryImageIndex--;
        }
    }

    public function saveImages(): void
    {
        if (!$this->canPublish) {
            $this->redirect(route('settings.subscription'));
            return;
        }
        
        $this->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|max:10240', // 10MB Max
        ]);

        foreach ($this->images as $index => $image) {
            $path = $image->store('property_images', 'public');
            $this->propertyListing->images()->create([
                'image_path' => $path,
                'image_url' => Storage::url($path),
                'is_primary' => $index === $this->primaryImageIndex,
            ]);
        }

        // Redirigir a página de matches encontrados
        $this->redirect(route('property-listings.matches-found', ['id' => $this->propertyListing->id]));
    }

    public function grantPremiumRole(): void
    {
        $user = auth()->user();
        
        // Buscar el rol premium
        $premiumRole = \Spatie\Permission\Models\Role::where('name', 'premium')->first();
        
        if ($premiumRole && !$user->hasRole('premium')) {
            $user->assignRole('premium');
            $this->canPublish = true;
            session()->flash('success', '¡Rol premium otorgado exitosamente! Ahora puedes publicar anuncios.');
        }
    }
};
?>

<x-layouts.app>
    @volt('property-listings.create')
    <x-app.container>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('listings.create_listing') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if ($step == 1)
                    {{ __('listings.step_1_description') }}
                @else
                    {{ __('listings.step_2_description') }}
                @endif
            </p>
        </div>

        {{-- Mensajes flash --}}
        @if (session()->has('success'))
            <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-300">
                            {{ session('success') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ session('error') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (!$canPublish)
            <div class="mt-6 p-8 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-300">
                            {{ __('listings.premium_required') }}
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-400">
                            <p>{{ __('listings.premium_description') }}</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>{{ __('listings.premium_benefit_unlimited') }}</li>
                                <li>{{ __('listings.premium_benefit_notifications') }}</li>
                                <li>{{ __('listings.premium_benefit_stats') }}</li>
                                <li>{{ __('listings.premium_benefit_support') }}</li>
                            </ul>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <a href="{{ route('settings.subscription') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                {{ __('listings.get_premium') }}
                            </a>
                            <button wire:click="grantPremiumRole" type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('listings.already_premium') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-6">
            @if ($step == 1)
                <form wire:submit.prevent="save" class="space-y-8">
                    <div class="p-8 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">{{ __('listings.listing_details') }}</h2>
                        <div class="grid grid-cols-1 mt-6 gap-y-6 gap-x-4 sm:grid-cols-6">
                            
                            <div class="sm:col-span-2">
                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.country') }}</label>
                                <select wire:model.live="selectedCountry" id="country" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">{{ __('listings.select_country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('country') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            
                            <div class="sm:col-span-6">
                                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.title') }}</label>
                                <input type="text" wire:model="title" id="title" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-6">
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.description') }}</label>
                                <textarea wire:model="description" id="description" rows="4" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                @error('description') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-3">
                                <label for="property_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.property_type') }}</label>
                                <select wire:model="property_type" id="property_type" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="casa">{{ __('properties.types.house') }}</option>
                                    <option value="departamento">{{ __('properties.types.apartment') }}</option>
                                    <option value="local">{{ __('properties.types.commercial') }}</option>
                                    <option value="oficina">{{ __('properties.types.office') }}</option>
                                    <option value="terreno">{{ __('properties.types.land') }}</option>
                                    <option value="campo">{{ __('properties.types.farm') }}</option>
                                    <option value="galpon">{{ __('properties.types.warehouse') }}</option>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="transaction_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.transaction_type') }}</label>
                                <select wire:model="transaction_type" id="transaction_type" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="venta">{{ __('properties.transaction_types.sale') }}</option>
                                    <option value="alquiler">{{ __('properties.transaction_types.rent') }}</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.price') }}</label>
                                <input type="number" wire:model="price" id="price" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 @error('price') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.currency') }}</label>
                                <select wire:model="currency" id="currency" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @foreach($availableCurrencies as $currency_code)
                                        <option value="{{ $currency_code }}">{{ $currency_code }}</option>
                                    @endforeach
                                </select>
                                @error('currency') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>


                            <div class="sm:col-span-2">
                                <label for="bedrooms" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.bedrooms') }}</label>
                                <input type="number" wire:model="bedrooms" id="bedrooms" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 @error('bedrooms') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            
                            <div class="sm:col-span-2">
                                <label for="bathrooms" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.bathrooms') }}</label>
                                <input type="number" wire:model="bathrooms" id="bathrooms" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 @error('bathrooms') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="parking_spaces" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.parking_spaces') }}</label>
                                <input type="number" wire:model="parking_spaces" id="parking_spaces" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="area" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.covered_area') }}</label>
                                <input type="number" wire:model="area" id="area" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 @error('area') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="lotsize" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.land_area') }}</label>
                                <input type="number" wire:model="lotsize" id="lotsize" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 @error('lotsize') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>


                            <div class="sm:col-span-6">
                                <label for="conditions" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.conditions') }}</label>
                                <textarea wire:model="conditions" id="conditions" rows="4" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                @error('conditions') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="p-8 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700" x-data @country-selected-for-map.window="window.initAndPan()">
                        <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">{{ __('listings.form.location') }}</h2>
                        <div class="grid grid-cols-1 mt-6 gap-y-6 gap-x-4 sm:grid-cols-6">
                            
                            
                            <div class="sm:col-span-2">
                                <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.state') }}</label>
                                <select wire:model.live="selectedState" id="state" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">{{ __('listings.select_state') }}</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                                    @endforeach
                                </select>
                                 @error('state') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            
                            <div class="sm:col-span-2">
                                <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.city') }}</label>
                                <select wire:model="city" id="city" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">{{ __('listings.select_city') }}</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->name }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                 @error('city') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-6">
                                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.address') }}</label>
                                <div class="flex mt-1 space-x-2">
                                    <input type="text" wire:model="address" id="address" class="block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <button type="button" id="search-address-btn" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <x-phosphor-magnifying-glass class="w-4 h-4 mr-2" />
                                        {{ __('listings.search_location') }}
                                    </button>
                                </div>
                                @error('address') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.map_location') }}</label>

<div id="map" class="relative w-full h-64 mt-2 rounded-md bg-gray-100 dark:bg-gray-700" wire:ignore>
                                    <div id="map-placeholder" class="absolute top-0 left-0 z-10 flex items-center justify-center w-full h-full">
                                        <p class="text-gray-500 dark:text-gray-400">{{ __('listings.select_country_for_map') }}</p>
                                    </div>
                                </div>

                                <input type="hidden" wire:model="latitude" id="latitude">
                                <input type="hidden" wire:model="longitude" id="longitude">
                                @error('latitude') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('longitude') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>



                        </div>
                    </div>

                    <div class="flex justify-end pt-5">
                        <a href="{{ route('property-listings.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">{{ __('listings.form.cancel') }}</a>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center justify-center px-4 py-2 ml-3 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-75 disabled:cursor-wait">
                            <span wire:loading.remove wire:target="save">
                                {{ __('listings.next_add_images') }}
                            </span>
                            <span wire:loading wire:target="save">
                                <svg class="inline-block w-4 h-4 mr-2 animate-spin" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.4857 8.02381C14.4857 4.42133 11.5787 1.51429 8 1.51429C4.42133 1.51429 1.51429 4.42133 1.51429 8.02381C1.51429 11.6263 4.42133 14.5333 8 14.5333" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-25"></path><path d="M8 1.51429V4.51429" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75"></path></svg>
                                <span>{{ __('listings.saving') }}</span>
                            </span>
                        </button>
                    </div>
                </form>
            @else
                <form wire:submit.prevent="saveImages" class="space-y-8" x-data="imageResizer()" @upload-finished.window="isResizing = false">
                    <div class="p-8 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">{{ __('listings.upload_images') }}</h2>
                        <div class="grid grid-cols-1 mt-6 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="imageUploads" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.add_property_images') }}</label>
                                <input type="file" x-ref="imageInput" @change="handleFiles" id="imageUploads" multiple class="block w-full mt-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <div x-show="isResizing" class="mt-2 text-sm text-gray-500">
                                    <svg class="inline-block w-4 h-4 mr-2 animate-spin" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.4857 8.02381C14.4857 4.42133 11.5787 1.51429 8 1.51429C4.42133 1.51429 1.51429 4.42133 1.51429 8.02381C1.51429 11.6263 4.42133 14.5333 8 14.5333" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-25"></path><path d="M8 1.51429V4.51429" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75"></path></svg>
                                    <span>{{ __('listings.processing_images') }}</span>
                                </div>
                                @error('imageUploads.*') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                                @if ($images)
                                    <div class="grid grid-cols-2 gap-4 mt-4 sm:grid-cols-3 md:grid-cols-4">
                                        @foreach ($images as $index => $image)
                                            <div class="relative p-2 border-2 rounded-lg" :class="{{ $primaryImageIndex === $index ? 'border-indigo-500' : 'border-transparent' }}">
                                                <img src="{{ $image->temporaryUrl() }}" class="object-cover w-full h-32 rounded-lg">
                                                <button wire:click.prevent="removeImage({{ $index }})" class="absolute top-0 right-0 p-1 text-white bg-red-500 rounded-full shadow-md -mt-2 -mr-2 hover:bg-red-600 focus:outline-none">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                </button>
                                                <div class="mt-2 text-center">
                                                    <label class="flex items-center justify-center space-x-2 text-sm">
                                                        <input type="radio" wire:model="primaryImageIndex" value="{{ $index }}" name="primary_image">
                                                        <span>{{ __('listings.primary_image') }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-5">
                        <button type="button" wire:click="$set('step', 1)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Back</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveImages" class="inline-flex items-center justify-center px-4 py-2 ml-3 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-75 disabled:cursor-wait" @if (empty($images)) disabled @endif>
                            <span wire:loading.remove wire:target="saveImages">
                                Guardar Imágenes y Finalizar
                            </span>
                            <span wire:loading wire:target="saveImages">
                                <svg class="inline-block w-4 h-4 mr-2 animate-spin" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.4857 8.02381C14.4857 4.42133 11.5787 1.51429 8 1.51429C4.42133 1.51429 1.51429 4.42133 1.51429 8.02381C1.51429 11.6263 4.42133 14.5333 8 14.5333" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-25"></path><path d="M8 1.51429V4.51429" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75"></path></svg>
                                <span>Finalizando...</span>
                            </span>
                        </button>
                    </div>
                </form>
            @endif
            </div>
        @endif
    </x-app.container>
    @endvolt
    <x-slot:javascript>
        <script>
            function imageResizer() {
                return {
                    isResizing: false,
                    handleFiles() {
                        let files = this.$refs.imageInput.files;
                        if (!files.length) return;

                        this.isResizing = true;

                        let livewire = this.$wire;
                        let resizedFiles = [];
                        let filesToProcess = files.length;

                        for (let i = 0; i < files.length; i++) {
                            this.resize(files[i], (resizedBlob) => {
                                resizedFiles.push(new File([resizedBlob], files[i].name, { type: 'image/jpeg' }));
                                filesToProcess--;
                                if (filesToProcess === 0) {
                                    livewire.uploadMultiple('imageUploads', resizedFiles, () => {
                                        this.isResizing = false;
                                    });
                                    this.$refs.imageInput.value = ''; // Clear input
                                }
                            });
                        }
                    },
                    resize(file, callback) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = new Image();
                            img.onload = () => {
                                const canvas = document.createElement('canvas');
                                const MAX_SIZE = 1200;
                                let width = img.width;
                                let height = img.height;

                                if (width > height) {
                                    if (width > MAX_SIZE) {
                                        height *= MAX_SIZE / width;
                                        width = MAX_SIZE;
                                    }
                                } else {
                                    if (height > MAX_SIZE) {
                                        width *= MAX_SIZE / height;
                                        height = MAX_SIZE;
                                    }
                                }
                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);
                                canvas.toBlob(callback, 'image/jpeg', 0.85);
                            };
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                }
            }
        </script>

        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        
        <script>
            window.map = null;
            window.marker = null;

            window.initAndPan = function() {
                if (!window.map) {
                    window.initMap();
                }
                // Use a timeout to allow the map to pan smoothly after initialization or on subsequent selections
                setTimeout(() => {
                    window.handleLocationChange();
                }, 100);
            }

            window.initMap = function() {
                const placeholder = document.getElementById('map-placeholder');
                if (placeholder) {
                    placeholder.remove();
                }

                var container = L.DomUtil.get('map');
                if(container != null){
                    container._leaflet_id = null;
                }
                
                window.map = L.map('map').setView([20, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(window.map);

                window.map.on('click', function(e) {
                    window.updateMarkerAndInputs(e.latlng.lat, e.latlng.lng);
                });

                // Attach listeners for geocoding now that the map is ready
                document.getElementById('search-address-btn').addEventListener('click', window.searchAddress);
                document.getElementById('state').addEventListener('change', window.handleLocationChange);
                document.getElementById('city').addEventListener('change', window.handleLocationChange);
            }

            window.updateMarkerAndInputs = function(lat, lng) {
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('latitude').dispatchEvent(new Event('input'));
                document.getElementById('longitude').dispatchEvent(new Event('input'));

                if (window.marker) {
                    window.marker.setLatLng([lat, lng]);
                } else {
                    window.marker = L.marker([lat, lng]).addTo(window.map);
                }
            }

            window.handleLocationChange = function() {
                if (!window.map) return;

                const countrySelect = document.getElementById('country');
                const stateSelect = document.getElementById('state');
                const citySelect = document.getElementById('city');

                const country = countrySelect.options[countrySelect.selectedIndex]?.text;
                const state = stateSelect.options[stateSelect.selectedIndex]?.text;
                const city = citySelect.value;

                let queryParts = [];
                let zoom = 2;

                if (city && city !== 'Select a city' && city !== '') {
                    queryParts.push(city);
                    zoom = 12;
                }
                if (state && state !== 'Select a state' && state !== '') {
                    queryParts.push(state);
                    if (zoom < 8) zoom = 8;
                }
                if (country && country !== 'Select a country' && country !== '') {
                    queryParts.push(country);
                    if (zoom < 5) zoom = 5;
                }

                const query = queryParts.join(', ');

                if (query) {
                    fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                const { lat, lon } = data[0];
                                window.map.flyTo([parseFloat(lat), parseFloat(lon)], zoom);
                            }
                        })
                        .catch(error => console.error('Error geocoding:', error));
                }
            }

            window.searchAddress = function() {
                if (!window.map) return;

                const addressInput = document.getElementById('address');
                const countrySelect = document.getElementById('country');
                const stateSelect = document.getElementById('state');
                const citySelect = document.getElementById('city');

                const address = addressInput.value;
                if (!address) return;

                const country = countrySelect.options[countrySelect.selectedIndex]?.text;
                const state = stateSelect.options[stateSelect.selectedIndex]?.text;
                const city = citySelect.value;

                let queryParts = [address];
                if (city && city !== 'Select a city' && city !== '') queryParts.push(city);
                if (state && state !== 'Select a state' && state !== '') queryParts.push(state);
                if (country && country !== 'Select a country' && country !== '') queryParts.push(country);

                const query = queryParts.join(', ');

                fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            const { lat, lon } = data[0];
                            const parsedLat = parseFloat(lat);
                            const parsedLon = parseFloat(lon);
                            window.map.flyTo([parsedLat, parsedLon], 16);
                            window.updateMarkerAndInputs(parsedLat, parsedLon);
                        } else {
                            alert('Address not found. Please try a different address or click on the map to set the location manually.');
                        }
                    })
                    .catch(error => {
                        console.error('Error geocoding address:', error);
                        alert('An error occurred while searching for the address.');
                    });
            }
        </script>
    </x-slot:javascript>
</x-layouts.app>