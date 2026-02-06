<x-layouts.marketing :seo="$seo ?? null">
    
    <!-- Hero Section with Search -->
    <section class="relative bg-gradient-to-r from-gray-100 to-gray-200 py-10">
        <div class="absolute inset-0 bg-white/10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    {{ __('properties.search_hero_title') }}
                </h1>
                <p class="text-xl text-gray-700 mb-8 max-w-3xl mx-auto">
                    {{ __('properties.search_hero_subtitle') }}
                </p>
                
                <!-- Search Form -->
                <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-6">
                    <form method="GET" action="{{ route_localized('property.search') }}" class="space-y-4" id="propertySearchForm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Country Selection -->
                            <div class="md:col-span-1">
                                <label for="country" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                    {{ __('properties.search_form.country_label') }} <span class="text-red-500">*</span>
                                    <span class="text-gray-500 text-xs">{{ __('properties.search_form.country_required') }}</span>
                                </label>
                                <select name="country" id="country" class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" required>
                                    <option value="">{{ __('properties.search_form.select_country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country }}" {{ $selectedCountry === $country ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- Search Input -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                    {{ __('properties.search_form.what_looking_for') }} <span class="text-red-500">*</span>
                                    <span class="text-gray-500 text-xs">{{ __('properties.search_form.min_chars') }}</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        name="search" 
                                        id="search"
                                        value="{{ $searchTerm }}"
                                        placeholder="{{ __('properties.search_form.placeholder') }}" 
                                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                        required
                                        minlength="5"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <!-- Character counter -->
                                    <div id="charCounter" class="absolute -bottom-5 right-0 text-xs text-gray-500">
                                        <span id="charCount">0</span>/5 {{ __('properties.search_form.char_counter') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button 
                                type="submit" 
                                id="searchButton"
                                class="inline-flex items-center justify-center px-8 py-3 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 min-w-[140px] disabled:bg-blue-400 disabled:cursor-not-allowed transition-colors duration-200"
                            >
                                <!-- Default state -->
                                <span id="searchButtonDefault" class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    {{ __('properties.search_form.search_button') }}
                                </span>
                                
                                <!-- Loading state -->
                                <span id="searchButtonLoading" class="hidden flex items-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('properties.search_form.searching') }}
                                </span>
                            </button>
                            
                            @if($searchTerm || $selectedCountry)
                                <a 
                                    href="{{ route_localized('property.search') }}" 
                                    class="inline-flex items-center px-6 py-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    {{ __('properties.search_form.clear') }}
                                </a>
                            @endif
                        </div>
                    </form>
                    
                    <!-- Validation Errors -->
                    @if(!empty($validationErrors) && $isSearchRequest)
                        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">
                                        {{ __('properties.search_form.check_fields') }}
                                    </h3>
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
        </div>
    </section>

    <!-- Results Section -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Loading Indicator (shown during search) -->
            <div id="searchLoadingIndicator" class="hidden mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                    <div class="flex items-center justify-center">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                            </div>
                            <div class="text-left">
                                <div class="text-lg font-semibold text-gray-900">{{ __('properties.search_form.searching_properties') }}</div>
                                <div id="loadingStatusText" class="text-sm text-gray-600">{{ __('properties.search_form.processing_search') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            @if($totalResults > 0)
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        {{ __('properties.search_results.title') }}
                    </h2>
                    <p class="text-gray-600">
                        {{ number_format($totalResults) }} {{ $totalResults === 1 ? __('properties.search_results.property_found') : __('properties.search_results.properties_found') }}
                        @if($selectedCountry)
                            {{ __('properties.search_results.in_country', ['country' => $selectedCountry]) }}
                        @endif
                        @if($searchTerm)
                            {{ __('properties.search_results.for_term', ['term' => $searchTerm]) }}
                        @endif
                        @if(isset($searchTime) && $searchTime > 0)
                            <span class="text-sm text-gray-500">
                                • {{ $searchTime }}ms
                                @if($searchTerm)
                                    <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        IA
                                    </span>
                                @endif
                            </span>
                        @endif
                    </p>
                </div>
            @endif

            <!-- Property Grid -->
            <div id="propertyResults" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($properties as $listing)
                    <!-- Property Card -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Property Image -->
                        <div class="relative">
                            @if($listing->primaryImage)
                                <img 
                                    src="{{ $listing->primaryImage->image_url }}" 
                                    alt="{{ $listing->title }}" 
                                    class="w-full h-48 object-cover"
                                >
                            @else
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            @endif
                            
                            <!-- Property Type Badge -->
                            <div class="absolute top-3 left-3">
                                <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-blue-600 rounded">
                                    {{ __('properties.transaction_types.' . $listing->transaction_type) }}
                                </span>
                            </div>
                            
                            <!-- Featured Badge -->
                            @if($listing->is_featured)
                                <div class="absolute top-3 right-3">
                                    <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-yellow-500 rounded">
                                        {{ __('properties.search_results.featured') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Property Info -->
                        <div class="p-4">
                            <!-- Price -->
                            <div class="mb-2">
                                <span class="text-2xl font-bold text-green-600">
                                    {{ $listing->currency }} {{ number_format($listing->price) }}
                                </span>
                            </div>
                            
                            <!-- Title -->
                            <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                {{ $listing->getTranslation('title', app()->getLocale()) }}
                            </h3>
                            
                            <!-- Location -->
                            <p class="text-sm text-gray-600 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                                {{ $listing->city }}, {{ $listing->state }}, {{ $listing->country }}
                            </p>
                            
                            <!-- Property Details -->
                            <div class="grid grid-cols-3 gap-2 text-sm text-gray-600 mb-3">
                                @if($listing->bedrooms)
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                        </svg>
                                        {{ $listing->bedrooms }}
                                    </div>
                                @endif
                                @if($listing->bathrooms)
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd" />
                                        </svg>
                                        {{ $listing->bathrooms }}
                                    </div>
                                @endif
                                @if($listing->area)
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        {{ number_format($listing->area) }} m²
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Property Type -->
                            <p class="text-sm text-gray-500 mb-3">
                                {{ __('properties.types.' . $listing->property_type) }}
                            </p>
                            
                            <!-- Similarity Score (only if search term is used) -->
                            @if($searchTerm && isset($listing->similarity))
                                <div class="mb-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ __('properties.search_results.relevance') }}:</span>
                                        <span class="font-medium text-green-600">{{ number_format($listing->similarity, 0) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $listing->similarity }}%"></div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Action Button -->
                            @php
                                $locale = app()->getLocale();
                                $countrySlug = Str::slug($listing->country);
                                $citySlug = Str::slug($listing->city);
                                $titleSlug = Str::slug($listing->title);
                                $propertyUrl = "/{$locale}/{$countrySlug}/{$citySlug}/propiedad/{$listing->id}-{$titleSlug}";
                            @endphp
                            <a 
                                href="{{ $propertyUrl }}"
                                class="block w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors duration-200 font-medium text-center"
                            >
                                {{ __('properties.view_details') }}
                            </a>
                        </div>
                    </div>
                @empty
                    @if($searchTerm || $selectedCountry)
                        <!-- No Results -->
                        <div class="col-span-full">
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('properties.search_results.no_properties_found') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('properties.search_results.try_different_search') }}
                                </p>
                                <div class="mt-6">
                                    <a href="{{ route_localized('property.search') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        {{ __('properties.search_form.clear_filters') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    @if($properties->isEmpty() && !$searchTerm && !$selectedCountry)
        <section class="py-8 bg-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">
                    {{ __('properties.cta.ready_title') }}
                </h2>
                <p class="text-xl text-gray-600 mb-8">
                    {{ __('properties.cta.ready_subtitle') }}
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('properties.cta.smart_search_title') }}</h3>
                        <p class="text-gray-600">{{ __('properties.cta.smart_search_desc') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('properties.cta.multiple_locations_title') }}</h3>
                        <p class="text-gray-600">{{ __('properties.cta.multiple_locations_desc') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('properties.cta.relevant_results_title') }}</h3>
                        <p class="text-gray-600">{{ __('properties.cta.relevant_results_desc') }}</p>
                    </div>
                </div>
                <p class="text-gray-500">
                    {{ __('properties.cta.search_hint') }}
                </p>
            </div>
        </section>
    @endif

    <!-- Loading State JavaScript -->
    <script>
        // Translations for JavaScript
        const translations = {
            generatingEmbeddings: '{{ __('properties.js.generating_embeddings') }}',
            analyzingSimilarities: '{{ __('properties.js.analyzing_similarities') }}',
            sortingResults: '{{ __('properties.js.sorting_results') }}',
            filteringProperties: '{{ __('properties.js.filtering_properties') }}',
            smartSearchProgress: '{{ __('properties.js.smart_search_progress') }}',
            searchingProperties: '{{ __('properties.js.searching_properties') }}',
            selectCountryError: '{{ __('properties.js.select_country_error') }}',
            enterSearchError: '{{ __('properties.js.enter_search_error') }}',
            minCharsError: '{{ __('properties.js.min_chars_error') }}',
            checkFields: '{{ __('properties.search_form.check_fields') }}'
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('propertySearchForm');
            const searchButton = document.getElementById('searchButton');
            const defaultState = document.getElementById('searchButtonDefault');
            const loadingState = document.getElementById('searchButtonLoading');
            const searchInput = document.getElementById('search');
            const countrySelect = document.getElementById('country');
            const loadingIndicator = document.getElementById('searchLoadingIndicator');
            const propertyResults = document.getElementById('propertyResults');
            const statusText = document.getElementById('loadingStatusText');
            
            // Flag to track if user has interacted with the form
            let userHasInteracted = false;
            
            // Check if page was loaded with search parameters (indicates this is a search result page)
            const urlParams = new URLSearchParams(window.location.search);
            const hasSearchParams = urlParams.has('search') || urlParams.has('country');
            
            if (hasSearchParams) {
                userHasInteracted = true; // Allow validation if this is a search result page
            }

            if (form && searchButton) {
                form.addEventListener('submit', function(e) {
                    // Only show loading if there's actually a search term or country selected
                    const hasSearchTerm = searchInput.value.trim().length > 0;
                    const hasCountrySelected = countrySelect.value.length > 0;
                    
                    if (hasSearchTerm || hasCountrySelected) {
                        // Switch to loading state
                        searchButton.disabled = true;
                        defaultState.classList.add('hidden');
                        loadingState.classList.remove('hidden');
                        
                        // Show loading indicator in results section with smooth transition
                        if (loadingIndicator && propertyResults) {
                            loadingIndicator.classList.remove('hidden');
                            // Add smooth fade effect
                            loadingIndicator.style.opacity = '0';
                            setTimeout(() => {
                                loadingIndicator.style.opacity = '1';
                                loadingIndicator.style.transition = 'opacity 0.3s ease-in-out';
                            }, 100);
                            
                            propertyResults.style.opacity = '0.3';
                            propertyResults.style.transition = 'opacity 0.3s ease-in-out';
                        }
                        
                        // Update status text based on search type
                        if (statusText) {
                            if (hasSearchTerm) {
                                // Simulate progressive loading messages for embedding search
                                setTimeout(() => {
                                    statusText.textContent = translations.generatingEmbeddings;
                                }, 500);
                                setTimeout(() => {
                                    statusText.textContent = translations.analyzingSimilarities;
                                }, 2000);
                                setTimeout(() => {
                                    statusText.textContent = translations.sortingResults;
                                }, 3500);
                            } else {
                                statusText.textContent = translations.filteringProperties;
                            }
                        }
                        
                        // Add a subtle pulse effect to the search form
                        document.querySelector('.bg-white.rounded-lg.shadow-2xl').classList.add('animate-pulse');
                        
                        // Optional: Show a loading message below form
                        const existingMessage = document.getElementById('searchLoadingMessage');
                        if (!existingMessage) {
                            const loadingMessage = document.createElement('div');
                            loadingMessage.id = 'searchLoadingMessage';
                            loadingMessage.className = 'mt-4 text-center text-sm text-blue-600 font-medium';
                            loadingMessage.innerHTML = `
                                <div class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    ${hasSearchTerm ? translations.smartSearchProgress : translations.searchingProperties}
                                </div>
                            `;
                            
                            // Insert the loading message after the form
                            form.parentNode.appendChild(loadingMessage);
                        }
                    }
                });
                
                // Reset button state if user navigates back
                window.addEventListener('pageshow', function(event) {
                    if (event.persisted) {
                        resetLoadingState();
                    }
                });
                
                // Also reset on page load (for back button scenarios)
                window.addEventListener('load', function() {
                    resetLoadingState();
                });
                
                function resetLoadingState() {
                    searchButton.disabled = false;
                    defaultState.classList.remove('hidden');
                    loadingState.classList.add('hidden');
                    
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('hidden');
                    }
                    
                    if (propertyResults) {
                        propertyResults.style.opacity = '1';
                    }
                    
                    // Remove loading message if it exists
                    const loadingMessage = document.getElementById('searchLoadingMessage');
                    if (loadingMessage) {
                        loadingMessage.remove();
                    }
                    
                    // Remove validation errors
                    const validationErrors = document.getElementById('clientValidationErrors');
                    if (validationErrors) {
                        validationErrors.remove();
                    }
                    
                    // Remove pulse effect
                    const formContainer = document.querySelector('.bg-white.rounded-lg.shadow-2xl');
                    if (formContainer) {
                        formContainer.classList.remove('animate-pulse');
                    }
                }
                
                // Validation helper functions
                function showValidationErrors(errors) {
                    clearValidationErrors();
                    
                    const errorContainer = document.createElement('div');
                    errorContainer.id = 'clientValidationErrors';
                    errorContainer.className = 'mt-4 p-4 bg-red-50 border border-red-200 rounded-md';
                    errorContainer.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    ${translations.checkFields}
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        ${errors.map(error => `<li>${error}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    form.parentNode.appendChild(errorContainer);
                    
                    // Scroll to the error
                    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                function clearValidationErrors() {
                    const existingErrors = document.getElementById('clientValidationErrors');
                    if (existingErrors) {
                        existingErrors.remove();
                    }
                }
                
                // Real-time validation feedback
                function updateInputValidation() {
                    const searchTerm = searchInput.value.trim();
                    const countrySelected = countrySelect.value.trim();
                    const charCounter = document.getElementById('charCounter');
                    const charCount = document.getElementById('charCount');
                    
                    // Update character counter (always visible now)
                    if (charCount) {
                        charCount.textContent = searchTerm.length;
                        
                        if (searchTerm.length < 5) {
                            charCounter.classList.add('text-red-500');
                            charCounter.classList.remove('text-green-500');
                        } else {
                            charCounter.classList.add('text-green-500');
                            charCounter.classList.remove('text-red-500');
                        }
                    }
                    
                    // Remove previous styling
                    searchInput.classList.remove('border-red-300', 'border-green-300');
                    countrySelect.classList.remove('border-red-300', 'border-green-300');
                    
                    // Validate country selection
                    if (countrySelected) {
                        countrySelect.classList.add('border-green-300');
                    } else {
                        countrySelect.classList.add('border-red-300');
                    }
                    
                    // Validate search term
                    if (searchTerm.length >= 5) {
                        searchInput.classList.add('border-green-300');
                    } else if (searchTerm.length > 0) {
                        searchInput.classList.add('border-red-300');
                    }
                    
                    // Update button state
                    if (countrySelected && searchTerm.length >= 5) {
                        searchButton.disabled = false;
                        clearValidationErrors();
                    } else {
                        // Don't disable here to allow HTML5 validation to work
                    }
                }
                
                // Add real-time validation
                if (searchInput && countrySelect) {
                    searchInput.addEventListener('input', updateInputValidation);
                    countrySelect.addEventListener('change', updateInputValidation);
                    
                    // Only run initial validation if there are existing values
                    const hasInitialValues = searchInput.value.trim() || countrySelect.value.trim();
                    if (hasInitialValues) {
                        updateInputValidation();
                    }
                }
                
                // Override the form submission
                const originalSubmit = form.onsubmit;
                form.onsubmit = function(e) {
                    // Get current values
                    const searchTerm = searchInput.value.trim();
                    const countrySelected = countrySelect.value.trim();
                    
                    // Clear previous validation messages
                    clearValidationErrors();
                    
                    // Validation logic - both fields are required
                    let isValid = true;
                    let errorMessages = [];
                    
                    // Check country selection
                    if (!countrySelected) {
                        isValid = false;
                        errorMessages.push(translations.selectCountryError);
                    }
                    
                    // Check search term
                    if (!searchTerm) {
                        isValid = false;
                        errorMessages.push(translations.enterSearchError);
                    } else if (searchTerm.length < 5) {
                        isValid = false;
                        errorMessages.push(translations.minCharsError);
                    }
                    
                    if (!isValid) {
                        e.preventDefault(); // Prevent form submission
                        showValidationErrors(errorMessages);
                        return false;
                    }
                    
                    // If validation passes, proceed with normal submission
                    return true;
                };
            }
        });
    </script>

</x-layouts.marketing>