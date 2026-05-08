<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Pgvector\Laravel\Vector;
use App\Models\PropertyContact;
use App\Models\PropertyRequest;

middleware('auth');
name('property-listings.index');

new class extends Component {
    public $propertyListings;
    public $pagination = [];
    public array $contactCounts = [];
    public array $matchCounts = [];
    public string $searchTerm = '';
    public ?PropertyListing $listingToDelete = null;

    // Filtros
    public string $filterCountry = '';
    public string $filterPropertyType = '';
    public string $filterTransactionType = '';
    public string $filterState = '';
    public string $filterCity = '';
    public int $page = 1;

    // Opciones de filtros
    public array $countryOptions = [];
    public bool $hasMultipleCountries = false;
    public array $propertyTypeOptions = [];
    public array $transactionTypeOptions = [];
    public array $stateOptions = [];
    public array $cityOptions = [];

    public function mount(): void
    {
        $this->loadFilterOptions();
        $this->loadAllListings();
    }

    public function search(): void
    {
        $this->page = 1;

        if (empty($this->searchTerm)) {
            $this->loadAllListings();
            return;
        }

        try {
            $client = \OpenAI::client(config('openai.api_key'));
            $model = config('openai.embeddings_model');

            $response = $client->embeddings()->create([
                'model' => $model,
                'input' => $this->searchTerm,
            ]);

            $embedding = new Vector($response->embeddings[0]->embedding);

            $query = PropertyListing::query()
                ->with(['primaryImage', 'firstImage'])
                ->select('*')
                ->selectRaw('(1 - (embedding <=> ?)) * 50 + 50 as similarity', [$embedding])
                ->where('user_id', auth()->id())
                ->whereRaw('(embedding <=> ?) < 0.5', [$embedding]);

            $this->applyFilters($query);

            $this->propertyListings = $query->orderByDesc('similarity')->get();
            $this->pagination = [];

        } catch (\Exception $e) {
            $this->dispatch('error', 'Could not perform search: ' . $e->getMessage());
            $this->loadAllListings();
        }
    }

    public function clear(): void
    {
        $this->searchTerm = '';
        $this->page = 1;
        $this->loadAllListings();
    }

    public function updatedFilterCountry(): void
    {
        $this->page = 1;
        $this->filterState = '';
        $this->filterCity = '';
        $this->loadStateOptions();
        $this->loadCityOptions();
        $this->loadAllListings();
    }

    public function updatedFilterPropertyType(): void
    {
        $this->page = 1;
        $this->loadAllListings();
    }

    public function updatedFilterTransactionType(): void
    {
        $this->page = 1;
        $this->loadAllListings();
    }

    public function updatedFilterState(): void
    {
        $this->page = 1;
        $this->filterCity = '';
        $this->loadCityOptions();
        $this->loadAllListings();
    }

    public function updatedFilterCity(): void
    {
        $this->page = 1;
        $this->loadAllListings();
    }

    public function clearFilters(): void
    {
        $this->filterCountry = '';
        $this->filterPropertyType = '';
        $this->filterTransactionType = '';
        $this->filterState = '';
        $this->filterCity = '';
        $this->page = 1;
        $this->loadStateOptions();
        $this->loadCityOptions();
        $this->loadAllListings();
    }

    public function goToPage(int $targetPage): void
    {
        $this->page = $targetPage;
        $this->loadAllListings();
    }

    public function confirmDelete(int $listingId): void
    {
        $this->listingToDelete = PropertyListing::where('user_id', auth()->id())->findOrFail($listingId);
    }

    public function delete(): void
    {
        if (!$this->listingToDelete) {
            return;
        }

        if ($this->listingToDelete->user_id !== auth()->id()) {
            $this->cancelDelete();
            return;
        }

        $this->listingToDelete->load('images');

        foreach ($this->listingToDelete->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $listingId = $this->listingToDelete->id;
        $this->listingToDelete->delete();

        Cache::forget("matches_listing_count_{$listingId}");
        Cache::forget("matches_listing_{$listingId}");

        $this->loadFilterOptions();
        $this->loadAllListings();
        $this->cancelDelete();
    }

    public function cancelDelete(): void
    {
        $this->listingToDelete = null;
    }

    public function renewListing(int $listingId): void
    {
        $listing = PropertyListing::where('user_id', auth()->id())->findOrFail($listingId);

        if (!auth()->user()->hasRole('premium')) {
            return;
        }

        if ($listing->created_at->gt(now()->subDays(30))) {
            return;
        }

        $listing->timestamps = false;
        $listing->created_at = now();
        $listing->save();
        $listing->timestamps = true;

        Cache::forget("matches_listing_count_{$listingId}");
        Cache::forget("matches_listing_{$listingId}");

        $this->loadAllListings();

        session()->flash('success', __('listings.renewed_success'));
    }

    public function getActiveFilterCount(): int
    {
        return (int) !empty($this->filterCountry)
            + (int) !empty($this->filterPropertyType)
            + (int) !empty($this->filterTransactionType)
            + (int) !empty($this->filterState)
            + (int) !empty($this->filterCity);
    }

    private function loadFilterOptions(): void
    {
        $userId = auth()->id();

        // Países con anuncios del usuario (solo mostrar filtro si hay más de uno)
        $countries = PropertyListing::where('user_id', $userId)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->toArray();

        $this->hasMultipleCountries = count($countries) > 1;
        $this->countryOptions = $countries;

        // Tipos de inmueble usados por este usuario
        $this->propertyTypeOptions = PropertyListing::where('user_id', $userId)
            ->whereNotNull('property_type')
            ->where('property_type', '!=', '')
            ->distinct()
            ->orderBy('property_type')
            ->pluck('property_type')
            ->toArray();

        // Tipos de operación usados por este usuario
        $this->transactionTypeOptions = PropertyListing::where('user_id', $userId)
            ->whereNotNull('transaction_type')
            ->where('transaction_type', '!=', '')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type')
            ->toArray();

        $this->loadStateOptions();
        $this->loadCityOptions();
    }

    private function loadStateOptions(): void
    {
        $query = PropertyListing::where('user_id', auth()->id())
            ->whereNotNull('state')
            ->where('state', '!=', '');

        if (!empty($this->filterCountry)) {
            $query->where('country', $this->filterCountry);
        }

        $this->stateOptions = $query->distinct()->orderBy('state')->pluck('state')->toArray();
    }

    private function loadCityOptions(): void
    {
        $query = PropertyListing::where('user_id', auth()->id())
            ->whereNotNull('city')
            ->where('city', '!=', '');

        if (!empty($this->filterCountry)) {
            $query->where('country', $this->filterCountry);
        }

        if (!empty($this->filterState)) {
            $query->where('state', $this->filterState);
        }

        $this->cityOptions = $query->distinct()->orderBy('city')->pluck('city')->toArray();
    }

    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query): void
    {
        if (!empty($this->filterCountry)) {
            $query->where('country', $this->filterCountry);
        }
        if (!empty($this->filterPropertyType)) {
            $query->where('property_type', $this->filterPropertyType);
        }
        if (!empty($this->filterTransactionType)) {
            $query->where('transaction_type', $this->filterTransactionType);
        }
        if (!empty($this->filterState)) {
            $query->where('state', $this->filterState);
        }
        if (!empty($this->filterCity)) {
            $query->where('city', $this->filterCity);
        }
    }

    private function loadAllListings(): void
    {
        $perPage = 30;

        $query = PropertyListing::where('user_id', auth()->id())
            ->with(['primaryImage', 'firstImage'])
            ->latest();

        $this->applyFilters($query);

        $paginator = $query->paginate($perPage, ['*'], 'page', $this->page);

        $this->propertyListings = collect($paginator->items());

        $this->pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'path'         => $paginator->path(),
            'query'        => $paginator->getOptions()['query'] ?? [],
        ];

        $ids = $this->propertyListings->pluck('id')->toArray();
        $this->contactCounts = PropertyContact::whereIn('listing_id', $ids)
            ->selectRaw('listing_id, count(*) as total')
            ->groupBy('listing_id')
            ->pluck('total', 'listing_id')
            ->toArray();

        $this->matchCounts = [];
        foreach ($this->propertyListings as $listing) {
            $fullCache = Cache::get("matches_listing_{$listing->id}");
            if ($fullCache !== null) {
                $this->matchCounts[$listing->id] = $fullCache->count();
                continue;
            }

            $this->matchCounts[$listing->id] = Cache::remember(
                "matches_listing_count_{$listing->id}",
                14400,
                function () use ($listing) {
                    return PropertyRequest::active()
                        ->whereRaw('LOWER(country) = LOWER(?)', [$listing->country])
                        ->whereRaw('LOWER(property_type) = LOWER(?)', [$listing->property_type])
                        ->whereRaw('LOWER(transaction_type) = LOWER(?)', [$listing->transaction_type])
                        ->where(function ($q) use ($listing) {
                            $q->whereNull('max_budget')
                              ->orWhere('max_budget', '=', 0)
                              ->orWhere('max_budget', '>=', $listing->price);
                        })
                        ->where(function ($q) use ($listing) {
                            $q->whereNull('min_budget')
                              ->orWhere('min_budget', '<=', $listing->price);
                        })
                        ->where(function ($q) use ($listing) {
                            $q->whereNull('city')
                              ->orWhere('city', $listing->city)
                              ->orWhere('state', $listing->state);
                        })
                        ->count(\Illuminate\Support\Facades\DB::raw('DISTINCT COALESCE(client_email, id::text)'));
                }
            );
        }
    }
};
?>

<x-layouts.app>
    @volt('property-listings')
    <x-app.container>
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('listings.my_listings') }}</h1>
            <a href="{{ route('property-listings.create') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-900 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                {{ __('listings.publish') }}
            </a>
        </div>

        @if(session('success'))
            <div class="mt-4 px-4 py-3 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Búsqueda --}}
        <div class="mt-6">
            <form wire:submit.prevent="search" class="flex items-center gap-2">
                <input type="text" wire:model="searchTerm"
                    placeholder="{{ __('listings.search_placeholder') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-75 disabled:cursor-not-allowed whitespace-nowrap">
                    <span wire:loading.remove wire:target="search">{{ __('listings.search') }}</span>
                    <span wire:loading wire:target="search">
                        <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
                @if($searchTerm)
                    <button type="button" wire:click="clear"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 whitespace-nowrap">
                        {{ __('listings.clear') }}
                    </button>
                @endif
            </form>
        </div>

        {{-- Filtros --}}
        <div class="mt-3">
            <div class="grid grid-cols-2 gap-2 {{ $hasMultipleCountries ? 'sm:grid-cols-3 lg:grid-cols-5' : 'sm:grid-cols-4' }}">
                {{-- País (solo visible si hay anuncios en más de un país) --}}
                @if($hasMultipleCountries)
                <div>
                    <select wire:model.live="filterCountry"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200
                            {{ $filterCountry ? 'border-indigo-400 dark:border-indigo-500 ring-1 ring-indigo-300' : '' }}">
                        <option value="">{{ __('listings.all_countries') }}</option>
                        @foreach($countryOptions as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                {{-- Tipo de inmueble --}}
                <div>
                    <select wire:model.live="filterPropertyType"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200
                            {{ $filterPropertyType ? 'border-indigo-400 dark:border-indigo-500 ring-1 ring-indigo-300' : '' }}">
                        <option value="">{{ __('listings.all_types') }}</option>
                        @foreach($propertyTypeOptions as $value)
                            <option value="{{ $value }}">{{ \App\Models\PropertyType::getLabel($value) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de operación --}}
                <div>
                    <select wire:model.live="filterTransactionType"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200
                            {{ $filterTransactionType ? 'border-indigo-400 dark:border-indigo-500 ring-1 ring-indigo-300' : '' }}">
                        <option value="">{{ __('listings.all_operations') }}</option>
                        @foreach($transactionTypeOptions as $value)
                            <option value="{{ $value }}">{{ \App\Models\TransactionType::getLabel($value) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Provincia --}}
                <div>
                    <select wire:model.live="filterState"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200
                            {{ $filterState ? 'border-indigo-400 dark:border-indigo-500 ring-1 ring-indigo-300' : '' }}">
                        <option value="">{{ __('listings.all_states') }}</option>
                        @foreach($stateOptions as $state)
                            <option value="{{ $state }}">{{ $state }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Localidad --}}
                <div>
                    <select wire:model.live="filterCity"
                        @if(!$filterState && empty($cityOptions)) disabled @endif
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200
                            {{ $filterCity ? 'border-indigo-400 dark:border-indigo-500 ring-1 ring-indigo-300' : '' }}
                            disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">{{ __('listings.all_cities') }}</option>
                        @foreach($cityOptions as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Badge filtros activos + botón limpiar --}}
            @php $activeFilters = $this->getActiveFilterCount(); @endphp
            @if($activeFilters > 0)
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {{ trans_choice('listings.active_filters', $activeFilters, ['count' => $activeFilters]) }}
                    </span>
                    <button wire:click="clearFilters" type="button"
                        class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 underline">
                        {{ __('listings.clear_filters') }}
                    </button>
                </div>
            @endif
        </div>

        <div wire:loading wire:target="search" class="flex items-center justify-center w-full p-4 mt-4 text-sm font-medium text-gray-500">
            <svg class="w-5 h-5 mr-3 -ml-1 text-indigo-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ __('listings.searching') }}
        </div>

        <div class="mt-6" wire:loading.remove wire:target="search">
            @php $listings = collect($propertyListings); @endphp

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($listings as $listing)
                    <div class="flex flex-col bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        @php
                            $seoService = app(\App\Services\SeoService::class);
                            $listingUrl = $seoService->generatePropertyUrl($listing, app()->getLocale());
                        @endphp
                        <div class="relative">
                            @php $displayImage = $listing->primaryImage ?? $listing->firstImage; @endphp
                            @if($displayImage)
                                <a href="{{ $listingUrl }}" target="_blank">
                                    <img src="{{ $displayImage->image_url }}" alt="{{ $listing->title }}" class="object-cover w-full h-48 rounded-t-lg hover:opacity-90 transition-opacity">
                                </a>
                            @else
                                <div class="flex items-center justify-center w-full h-48 text-gray-400 bg-gray-100 rounded-t-lg dark:bg-gray-700">
                                    {{ __('listings.no_image') }}
                                </div>
                            @endif
                            <div class="absolute top-2 right-2">
                                @if($listing->is_active)
                                    <span class="inline-flex px-2 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">{{ __('listings.active') }}</span>
                                @else
                                    <span class="inline-flex px-2 text-xs font-semibold leading-5 text-red-800 bg-red-100 rounded-full">{{ __('listings.inactive') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col flex-1 p-4">
                            <div>
                                <a href="{{ $listingUrl }}" target="_blank" class="text-lg font-bold text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $listing->title }}</a>
                                <p class="text-sm text-gray-500">{{ $listing->city }}, {{ $listing->state }}</p>
                            </div>

                            <div class="mt-2">
                                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $listing->currency }} {{ number_format($listing->price) }}</p>
                                <p class="text-sm text-gray-500">{{ \App\Models\PropertyType::getLabel($listing->property_type) }} / {{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}</p>
                            </div>

                            @if($searchTerm)
                                <div class="mt-2">
                                    <p class="text-sm font-medium text-green-600 dark:text-green-400">{{ __('listings.match') }}: {{ number_format($listing->similarity, 2) }}%</p>
                                </div>
                            @endif

                            @php $contactCount = $contactCounts[$listing->id] ?? 0; @endphp
                            @if ($contactCount > 0)
                            <div class="mt-3">
                                <a href="{{ route('dashboard.contacts.index', ['listing_id' => $listing->id]) }}"
                                   class="inline-flex items-center gap-1.5 text-sm text-orange-600 font-semibold hover:underline">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    {{ $contactCount }} contacto{{ $contactCount !== 1 ? 's' : '' }}
                                </a>
                            </div>
                            @endif

                            @php $matchCount = $matchCounts[$listing->id] ?? 0; @endphp
                            <div class="mt-1.5">
                                <a href="{{ route('dashboard.matches.show', $listing->id) }}"
                                   class="inline-flex items-center gap-1.5 text-sm {{ $matchCount > 0 ? 'text-indigo-600 font-semibold' : 'text-gray-400' }} hover:underline">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ $matchCount }} match{{ $matchCount !== 1 ? 'es' : '' }}
                                </a>
                            </div>
                        </div>

                        <div class="p-4 mt-auto bg-gray-50 dark:bg-gray-900/50 rounded-b-lg">
                            <div class="flex items-center gap-3">
                                <a href="/property-listings/{{ $listing->id }}/images"
                                   class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400">
                                    {{ __('listings.manage_images') }}
                                </a>
                                <a href="/property-listings/{{ $listing->id }}/edit"
                                   class="text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                                    {{ __('listings.edit') }}
                                </a>
                                @if(auth()->user()->hasRole('premium') && $listing->created_at->lt(now()->subDays(30)))
                                    <button wire:click="renewListing({{ $listing->id }})"
                                            wire:confirm="{{ __('listings.confirm_renew') }}"
                                            class="text-sm font-medium text-green-600 hover:text-green-800 dark:text-green-400">
                                        {{ __('listings.renew') }}
                                    </button>
                                @endif
                                <button wire:click="confirmDelete({{ $listing->id }})" class="text-sm font-medium text-red-600 hover:text-red-900">{{ __('listings.delete') }}</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-1 sm:col-span-2 lg:col-span-3">
                        <div class="py-12 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('listings.no_listings') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('listings.no_listings_desc') }}</p>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Paginación --}}
            @if(!empty($pagination) && ($pagination['last_page'] ?? 1) > 1)
                @php
                    $currentPage = $pagination['current_page'];
                    $lastPage    = $pagination['last_page'];
                    $total       = $pagination['total'];
                    $perPage     = $pagination['per_page'];
                    $from        = ($currentPage - 1) * $perPage + 1;
                    $to          = min($currentPage * $perPage, $total);
                @endphp
                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Mostrando {{ $from }}–{{ $to }} de {{ $total }} anuncios
                    </p>
                    <div class="flex items-center gap-1">
                        {{-- Anterior --}}
                        <button wire:click="goToPage({{ $currentPage - 1 }})"
                            @disabled($currentPage <= 1)
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:text-gray-300
                                {{ $currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            ‹
                        </button>

                        {{-- Páginas --}}
                        @php
                            $start = max(1, $currentPage - 2);
                            $end   = min($lastPage, $currentPage + 2);
                        @endphp
                        @if($start > 1)
                            <button wire:click="goToPage(1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">1</button>
                            @if($start > 2)
                                <span class="px-2 text-gray-400">…</span>
                            @endif
                        @endif

                        @for($p = $start; $p <= $end; $p++)
                            <button wire:click="goToPage({{ $p }})"
                                class="px-3 py-1.5 text-sm border rounded-md
                                    {{ $p === $currentPage
                                        ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                                        : 'border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                {{ $p }}
                            </button>
                        @endfor

                        @if($end < $lastPage)
                            @if($end < $lastPage - 1)
                                <span class="px-2 text-gray-400">…</span>
                            @endif
                            <button wire:click="goToPage({{ $lastPage }})" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ $lastPage }}</button>
                        @endif

                        {{-- Siguiente --}}
                        <button wire:click="goToPage({{ $currentPage + 1 }})"
                            @disabled($currentPage >= $lastPage)
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:text-gray-300
                                {{ $currentPage >= $lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            ›
                        </button>
                    </div>
                </div>
            @endif
        </div>

        @if($listingToDelete)
        <div class="fixed inset-0 z-10 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-25" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 dark:bg-gray-800">
                    <div>
                        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                            <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100" id="modal-title">
                                {{ __('listings.confirm_delete_title') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('listings.confirm_delete_message') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button wire:click="delete" type="button" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:col-start-2 sm:text-sm">
                            {{ __('listings.delete') }}
                        </button>
                        <button wire:click="cancelDelete" type="button" class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                            {{ __('listings.cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </x-app.container>
    @endvolt
</x-layouts.app>
