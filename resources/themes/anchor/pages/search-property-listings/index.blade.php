<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use App\Models\PropertyListing;
use Illuminate\Database\Eloquent\Collection;
use Pgvector\Laravel\Vector;

middleware('auth');
name('search-property-listings.index');

new class extends Component {
    public Collection $propertyListings;
    public string $searchTerm = '';

    public function mount(): void
    {
        $this->propertyListings = new Collection();
    }

    public function search(): void
    {
        if (empty($this->searchTerm)) {
            $this->propertyListings = new Collection();
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

            $this->propertyListings = PropertyListing::query()
                ->with(['user', 'primaryImage'])
                ->select('*')
                ->selectRaw('(1 - (embedding <=> ?)) * 50 + 50 as similarity', [$embedding])
                ->whereRaw('(embedding <=> ?) < 0.5', [$embedding]) // Similarity threshold at 75%
                ->orderByDesc('similarity')
                ->get();

        } catch (\Exception $e) {
            // Handle exceptions, e.g., show an error message
            $this->dispatch('error', __('messages.search_error') . ': ' . $e->getMessage());
            $this->propertyListings = new Collection();
        }
    }

    public function clear(): void
    {
        $this->searchTerm = '';
        $this->propertyListings = new Collection();
    }
};
?>

<x-layouts.app>
    @volt('search-property-listings')
    <x-app.container>
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('properties.search_all_listings') }}</h1>
        </div>

        <div class="mt-6">
            <form wire:submit.prevent="search" class="flex items-center space-x-2">
                <input type="text" wire:model="searchTerm" placeholder="{{ __('properties.search_placeholder') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-75 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="search">{{ __('messages.search') }}</span>
                    <span wire:loading wire:target="search">
                        <svg class="w-5 h-5 mr-2 -ml-1 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        
                    </span>
                </button>
                @if($searchTerm)
                    <button type="button" wire:click="clear" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                        {{ __('messages.clear') }}
                    </button>
                @endif
            </form>
        </div>

        <div wire:loading wire:target="search" class="flex items-center justify-center w-full p-4 mt-4 text-sm font-medium text-gray-500">
            <svg class="w-5 h-5 mr-3 -ml-1 text-indigo-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ __('messages.searching') }}
        </div>

        <div class="mt-6" wire:loading.remove wire:target="search">
            <!-- Responsive Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($propertyListings as $listing)
                    <!-- Card Component -->
                    <div class="flex flex-col bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <!-- Image -->
                        <div class="relative">
                            @if($listing->primaryImage)
                                <img src="{{ $listing->primaryImage->image_url }}" alt="{{ $listing->title }}" class="object-cover w-full h-48 rounded-t-lg">
                            @else
                                <div class="flex items-center justify-center w-full h-48 text-gray-400 bg-gray-100 rounded-t-lg dark:bg-gray-700">
                                    {{ __('messages.no_image') }}
                                </div>
                            @endif
                            <!-- Status Badge -->
                            <div class="absolute top-2 right-2">
                                @if($listing->is_active)
                                    <span class="inline-flex px-2 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">{{ __('messages.active') }}</span>
                                @else
                                    <span class="inline-flex px-2 text-xs font-semibold leading-5 text-red-800 bg-red-100 rounded-full">{{ __('messages.inactive') }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="flex flex-col flex-1 p-4">
                            <!-- Title and Location -->
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $listing->title }}</h3>
                                <p class="text-sm text-gray-500">{{ $listing->city }}, {{ $listing->state }}</p>
                            </div>
                            
                            <!-- Price and Type -->
                            <div class="mt-2">
                                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $listing->currency }} {{ number_format($listing->price) }}</p>
                                <p class="text-sm text-gray-500">{{ __('properties.types.' . $listing->property_type) }} / {{ __('properties.transaction_types.' . $listing->transaction_type) }}</p>
                            </div>

                            <!-- Lister Info -->
                            <div class="mt-2">
                                <p class="text-sm text-gray-400">{{ __('properties.by') }}: {{ $listing->user->name }}</p>
                            </div>

                            <!-- Similarity -->
                            @if($searchTerm)
                                <div class="mt-2">
                                    <p class="text-sm font-medium text-green-600 dark:text-green-400">{{ __('properties.similarity') }}: {{ number_format($listing->similarity, 2) }}%</p>
                                </div>
                            @endif
                        </div>

                        <!-- Card Footer (Actions) -->
                        <div class="p-4 mt-auto bg-gray-50 dark:bg-gray-900/50 rounded-b-lg">
                            <div class="flex justify-end">
                                <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ __('properties.view_details') }}</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <!-- Empty State -->
                    <div class="col-span-1 sm:col-span-2 lg:col-span-3">
                        <div class="py-12 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('properties.no_listings_found') }}</h3>
                            @if($searchTerm)
                                <p class="mt-1 text-sm text-gray-500">{{ __('properties.adjust_search_term') }}</p>
                            @else
                                <p class="mt-1 text-sm text-gray-500">{{ __('properties.enter_search_term') }}</p>
                            @endif
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </x-app.container>
    @endvolt
</x-layouts.app>