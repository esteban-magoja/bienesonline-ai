<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Computed;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Models\CountrySetting;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;

middleware('auth');
name('property-listings.edit');

new class extends Component {
    public PropertyListing $listing;
    public bool $canEdit = false;

    #[Rule('required|string|max:255')]
    public string $title = '';

    #[Rule('required|string')]
    public string $description = '';

    #[Rule('required|string')]
    public string $property_type = '';

    #[Rule('required|string')]
    public string $transaction_type = '';

    #[Rule('required|numeric|min:0')]
    public string $price = '';

    #[Rule('nullable|string|max:3')]
    public string $currency = 'USD';

    #[Rule('required|integer|min:0')]
    public int $bedrooms = 0;

    #[Rule('required|integer|min:0')]
    public int $bathrooms = 0;

    #[Rule('integer|min:0')]
    public int $parking_spaces = 0;

    #[Rule('required|numeric|min:0')]
    public int $area = 0;

    #[Rule('nullable|integer|min:0')]
    public ?int $lotsize = 0;

    #[Rule('nullable|string|max:255')]
    public string $address = '';

    #[Rule('required')]
    public $selectedCountry = null;

    #[Rule('required')]
    public $selectedState = null;

    #[Rule('required|string|max:255')]
    public string $city = '';

    #[Rule('nullable|numeric')]
    public ?float $latitude = null;

    #[Rule('nullable|numeric')]
    public ?float $longitude = null;

    public $countries;
    public $states = [];
    public $cities = [];
    public $propertyTypes = [];
    public $transactionTypes = [];
    public $availableCurrencies = ['USD'];

    #[Computed]
    public function state()
    {
        return State::find($this->selectedState)?->name;
    }

    public function mount($id): void
    {
        $this->listing = PropertyListing::where('user_id', auth()->id())->findOrFail($id);

        $user = auth()->user();
        $this->canEdit = $user->hasRole('admin') || $user->hasRole('premium');

        // Pre-fill all fields
        $this->title           = $this->listing->title;
        $this->description     = $this->listing->description;
        $this->property_type   = $this->listing->property_type;
        $this->transaction_type= $this->listing->transaction_type;
        $this->price           = (string) $this->listing->price;
        $this->currency        = $this->listing->currency ?? 'USD';
        $this->bedrooms        = $this->listing->bedrooms ?? 0;
        $this->bathrooms       = $this->listing->bathrooms ?? 0;
        $this->parking_spaces  = $this->listing->parking_spaces ?? 0;
        $this->area            = $this->listing->area ?? 0;
        $this->lotsize         = $this->listing->lotsize;
        $this->address         = $this->listing->address ?? '';
        $this->city            = $this->listing->city ?? '';
        $this->latitude        = $this->listing->latitude;
        $this->longitude       = $this->listing->longitude;

        $this->countries = CountrySetting::getEnabledCountries();

        // Pre-load country
        $country = Country::where('name', $this->listing->country)->first();
        if ($country) {
            $this->selectedCountry = $country->id;
            $this->states = State::where('country_id', $country->id)->get();
            $this->propertyTypes = PropertyType::getByCountry($country->iso2);
            $this->transactionTypes = TransactionType::getByCountry($country->iso2);
            if (isset($country->currency['code'])) {
                $currencies = array_unique([$country->currency['code'], 'USD']);
                if ($country->iso2 === 'CL') {
                    $currencies[] = 'UF';
                }
                $this->availableCurrencies = $currencies;
            }
        }

        // Pre-load state
        $stateModel = State::where('name', $this->listing->state)
            ->where('country_id', $country?->id)
            ->first();
        if ($stateModel) {
            $this->selectedState = $stateModel->id;
            $this->cities = City::where('state_id', $stateModel->id)->get();
        }
    }

    public function updatedSelectedCountry($countryId)
    {
        $this->states = State::where('country_id', $countryId)->get();
        $this->selectedState = null;
        $this->cities = [];

        $country = Country::find($countryId);
        if ($country) {
            if (isset($country->currency['code'])) {
                $this->currency = $country->currency['code'];
                $currencies = array_unique([$country->currency['code'], 'USD']);
                if ($country->iso2 === 'CL') {
                    $currencies[] = 'UF';
                }
                $this->availableCurrencies = $currencies;
            }
            $this->propertyTypes = PropertyType::getByCountry($country->iso2);
            $this->transactionTypes = TransactionType::getByCountry($country->iso2);
            $this->property_type = '';
            $this->transaction_type = '';
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
        return ['countries' => CountrySetting::getEnabledCountries()];
    }

    public function save(): void
    {
        if (!$this->canEdit) {
            $this->redirect(route('settings.subscription'));
            return;
        }

        $validated = $this->validate();
        $validated['country'] = Country::find($this->selectedCountry)?->name ?? $this->listing->country;
        $validated['state']   = $this->state ?? $this->listing->state;
        $validated['latitude']  = $this->latitude;
        $validated['longitude'] = $this->longitude;

        // Remove Volt-computed keys not in DB
        unset($validated['selectedCountry'], $validated['selectedState']);

        $this->listing->update($validated);

        session()->flash('success', 'El anuncio fue modificado correctamente.');
        $this->redirect(route('property-listings.index'));
    }

    public function grantPremiumRole(): void
    {
        $user = auth()->user();
        $premiumRole = \Spatie\Permission\Models\Role::where('name', 'premium')->first();

        if ($premiumRole && !$user->hasRole('premium')) {
            $user->assignRole('premium');
            $this->canEdit = true;
            session()->flash('success', '¡Rol premium otorgado exitosamente! Ahora puedes editar anuncios.');
        }
    }
};
?>

<x-layouts.app>
    @volt('property-listings.edit')
    <x-app.container>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editar anuncio</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Modificá los datos de tu anuncio. Las fotos se gestionan por separado.</p>
            </div>
            <a href="{{ route('property-listings.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                ← Volver a mis anuncios
            </a>
        </div>

        @if (!$canEdit)
            <div class="mt-6">
                <div class="mb-6 p-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
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
                            </div>
                        </div>
                    </div>
                </div>
                <livewire:billing.checkout />
            </div>
        @else

        <form wire:submit.prevent="save" class="mt-6 space-y-8">

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
                        @error('selectedCountry') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <select wire:model="property_type" id="property_type" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" @if(empty($propertyTypes)) disabled @endif>
                            <option value="">{{ __('listings.select_property_type') }}</option>
                            @foreach($propertyTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label }}</option>
                            @endforeach
                        </select>
                        @error('property_type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label for="transaction_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('listings.form.transaction_type') }}</label>
                        <select wire:model="transaction_type" id="transaction_type" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" @if(empty($transactionTypes)) disabled @endif>
                            <option value="">{{ __('listings.select_transaction_type') }}</option>
                            @foreach($transactionTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label }}</option>
                            @endforeach
                        </select>
                        @error('transaction_type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
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
                    </div>
                </div>
            </div>

            {{-- Ubicación --}}
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
                        @error('selectedState') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
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
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-5 gap-3">
                <a href="{{ route('property-listings.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('listings.form.cancel') }}
                </a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-75 disabled:cursor-wait">
                    <span wire:loading.remove wire:target="save">Guardar cambios</span>
                    <span wire:loading wire:target="save">
                        <svg class="inline-block w-4 h-4 mr-2 animate-spin" viewBox="0 0 16 16" fill="none"><path d="M14.4857 8.02381C14.4857 4.42133 11.5787 1.51429 8 1.51429C4.42133 1.51429 1.51429 4.42133 1.51429 8.02381C1.51429 11.6263 4.42133 14.5333 8 14.5333" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-25"/><path d="M8 1.51429V4.51429" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75"/></svg>
                        Guardando...
                    </span>
                </button>
            </div>

        </form>
        @endif
    </x-app.container>
    @endvolt

    <x-slot:javascript>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script>
            let map = null;
            let marker = null;

            window.initAndPan = function() {
                const mapEl = document.getElementById('map');
                const placeholder = document.getElementById('map-placeholder');
                if (placeholder) placeholder.style.display = 'none';

                if (!map) {
                    map = L.map('map').setView([0, 0], 2);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    map.on('click', function(e) {
                        placeMarker(e.latlng.lat, e.latlng.lng);
                    });
                }

                setTimeout(() => map.invalidateSize(), 100);

                // If already has coordinates, place marker
                const lat = document.getElementById('latitude')?.value;
                const lng = document.getElementById('longitude')?.value;
                if (lat && lng) {
                    placeMarker(parseFloat(lat), parseFloat(lng));
                    map.setView([parseFloat(lat), parseFloat(lng)], 14);
                }
            };

            function placeMarker(lat, lng) {
                if (marker) map.removeLayer(marker);
                marker = L.marker([lat, lng]).addTo(map);
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                // Notify Livewire
                window.Livewire?.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'))?.set('latitude', lat);
                window.Livewire?.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'))?.set('longitude', lng);
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Init map with existing coordinates
                setTimeout(() => window.initAndPan(), 300);

                // Address search
                document.getElementById('search-address-btn')?.addEventListener('click', function() {
                    const address = document.getElementById('address').value;
                    if (!address) return;
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.length > 0) {
                                placeMarker(parseFloat(data[0].lat), parseFloat(data[0].lon));
                                map.setView([parseFloat(data[0].lat), parseFloat(data[0].lon)], 15);
                            }
                        });
                });
            });
        </script>
    </x-slot:javascript>
</x-layouts.app>
