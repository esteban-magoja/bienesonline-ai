@php
    $locale = app()->getLocale();
    $countrySlug = Str::slug($property->country);
    
    // Mapeo de transaction_type a slugs
    $transactionSlugs = [
        'sale' => ['es' => 'venta', 'en' => 'sale'],
        'rent' => ['es' => 'alquiler', 'en' => 'rent'],
        'temporary_rent' => ['es' => 'alquiler-temporal', 'en' => 'temporary-rent'],
    ];
    $transactionSlug = $transactionSlugs[$property->transaction_type][$locale] ?? null;
    
    // Mapeo de property_type a slugs
    $propertySlugs = [
        'house' => ['es' => 'casas', 'en' => 'houses'],
        'apartment' => ['es' => 'departamentos', 'en' => 'apartments'],
        'office' => ['es' => 'oficinas', 'en' => 'offices'],
        'commercial' => ['es' => 'locales', 'en' => 'commercials'],
        'land' => ['es' => 'terrenos', 'en' => 'lands'],
        'field' => ['es' => 'campos', 'en' => 'fields'],
        'farm' => ['es' => 'fincas', 'en' => 'farms'],
        'warehouse' => ['es' => 'galpones', 'en' => 'warehouses'],
    ];
    $propertySlug = $propertySlugs[$property->property_type][$locale] ?? null;
    
    $stateSlug = Str::slug($property->state);
    $citySlug = Str::slug($property->city);
@endphp

<x-layouts.marketing :seo="$seo">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""/>
    
    <style>
        /* Custom styles for Leaflet map */
        #propertyMap {
            z-index: 0;
            width: 100%;
            height: 320px;
        }
        .custom-marker {
            background: transparent;
            border: none;
        }
        .leaflet-popup-content-wrapper {
            border-radius: 8px;
            padding: 0;
        }
        .leaflet-popup-content {
            margin: 12px;
        }
        /* Ensure the map container is properly sized */
        .leaflet-container {
            width: 100%;
            height: 100%;
        }
    </style>
    
    <!-- Property Detail Hero Section -->
    <section class="py-8 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1 md:gap-3">
                    {{-- Home --}}
                    <li class="inline-flex items-center">
                        <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                            </svg>
                            {{ __('messages.home') }}
                        </a>
                    </li>
                    
                    {{-- País --}}
                    <li>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="/{{ $locale }}/{{ $countrySlug }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
                                {{ $property->country }}
                            </a>
                        </div>
                    </li>
                    
                    {{-- Tipo de Transacción --}}
                    @if($transactionSlug)
                        <li>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/{{ $locale }}/{{ $countrySlug }}/{{ $transactionSlug }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
                                    {{ __('properties.transaction_types.' . $property->transaction_type) }}
                                </a>
                            </div>
                        </li>
                    @endif
                    
                    {{-- Tipo de Propiedad --}}
                    @if($propertySlug)
                        <li>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/{{ $locale }}/{{ $countrySlug }}/{{ $transactionSlug }}/{{ $propertySlug }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
                                    {{ __('properties.types.' . $property->property_type) }}
                                </a>
                            </div>
                        </li>
                    @endif
                    
                    {{-- Estado --}}
                    @if($property->state)
                        <li>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/{{ $locale }}/{{ $countrySlug }}/{{ $transactionSlug }}/{{ $propertySlug }}/{{ $stateSlug }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
                                    {{ $property->state }}
                                </a>
                            </div>
                        </li>
                    @endif
                    
                    {{-- Ciudad --}}
                    @if($property->city)
                        <li>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/{{ $locale }}/{{ $countrySlug }}/{{ $transactionSlug }}/{{ $propertySlug }}/{{ $stateSlug }}/{{ $citySlug }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
                                    {{ $property->city }}
                                </a>
                            </div>
                        </li>
                    @endif
                    
                    {{-- Título de la propiedad (sin link) --}}
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-sm font-medium text-gray-500">{{ Str::limit($property->title, 40) }}</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Property Title and Price -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    {{ $property->title }}
                                </h1>
                                <p class="text-gray-600 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $property->address }}, {{ $property->city }}, {{ $property->state }}, {{ $property->country }}
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-4xl font-bold text-green-600">
                                    {{ $property->currency }} {{ number_format($property->price) }}
                                </div>
                                <div class="inline-block mt-2 px-3 py-1 text-sm font-semibold text-white bg-blue-600 rounded-full">
                                    {{ __('properties.transaction_types.' . $property->transaction_type) }}
                                </div>
                            </div>
                        </div>

                        <!-- Property Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-4 border-t border-gray-200">
                            @if($property->bedrooms)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <!-- Icono de cama más representativo -->
                                    <svg class="w-10 h-10 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5V19M3 16H21M21 19V13.2C21 12.0799 21 11.5198 20.782 11.092C20.5903 10.7157 20.2843 10.4097 19.908 10.218C19.4802 10 18.9201 10 17.8 10H11V15.7273M7 12H7.01M8 12C8 12.5523 7.55228 13 7 13C6.44772 13 6 12.5523 6 12C6 11.4477 6.44772 11 7 11C7.55228 11 8 11.4477 8 12Z"></path>
                                    </svg>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-900">{{ $property->bedrooms }}</div>
                                        <div class="text-sm text-gray-600">{{ __('properties.features.bedrooms') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($property->bathrooms)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <!-- Icono de ducha -->
                                    <svg class="w-10 h-10 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-width="2" d="M10 4 8 6"/><path stroke-width="2" d="M17 19v2"/><path stroke-width="2" d="M2 12h20"/><path stroke-width="2" d="M7 19v2"/><path stroke-width="2" d="M9 5 7.621 3.621A2.121 2.121 0 0 0 4 5v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>
                                    </svg>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-900">{{ $property->bathrooms }}</div>
                                        <div class="text-sm text-gray-600">{{ __('properties.features.bathrooms') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($property->area)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <svg class="w-10 h-10 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-900">{{ number_format($property->area) }}</div>
                                        <div class="text-sm text-gray-600">{{ __('properties.covered_area_sqm') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($property->parking_spaces)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <!-- Icono de automóvil -->
                                    <svg class="w-10 h-10 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                        <circle stroke-width="2" cx="7" cy="17" r="2"/><path stroke-width="2" d="M9 17h6"/><circle stroke-width="2" cx="17" cy="17" r="2"/>
                                    </svg>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-900">{{ $property->parking_spaces }}</div>
                                        <div class="text-sm text-gray-600">{{ __('properties.features.garages') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($property->lotsize)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <svg class="w-10 h-10 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.5 7a24 24 0 0 1 0 10"/><path d="M4.5 7a24 24 0 0 0 0 10"/><path d="M7 19.5a24 24 0 0 0 10 0"/><path d="M7 4.5a24 24 0 0 1 10 0"/><rect x="17" y="17" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><rect x="2" y="2" width="5" height="5" rx="1"/>
                                    </svg>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-900">{{ number_format($property->lotsize) }}</div>
                                        <div class="text-sm text-gray-600">{{ __('properties.land_area_sqm') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Image Gallery -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                        @if($property->images->count() > 0)
                            <!-- Main Image -->
                            <div class="relative aspect-[16/9] bg-gray-200" id="mainImageContainer">
                                <img 
                                    id="mainImage"
                                    src="{{ $property->images->where('is_primary', true)->first()?->image_url ?? $property->images->first()->image_url }}" 
                                    alt="{{ $property->title }}" 
                                    class="w-full h-full object-cover"
                                >
                                
                                <!-- Navigation Arrows -->
                                @if($property->images->count() > 1)
                                    <button 
                                        onclick="previousImage()" 
                                        class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition-colors"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                    </button>
                                    <button 
                                        onclick="nextImage()" 
                                        class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition-colors"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                @endif

                                <!-- Image Counter -->
                                <div class="absolute bottom-4 right-4 bg-black/50 text-white px-3 py-1 rounded-full text-sm">
                                    <span id="currentImageIndex">1</span> / {{ $property->images->count() }}
                                </div>
                            </div>

                            <!-- Thumbnail Gallery -->
                            @if($property->images->count() > 1)
                                <div class="p-4 bg-gray-50">
                                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2" id="thumbnailContainer">
                                        @foreach($property->images as $index => $image)
                                            <button 
                                                onclick="showImage({{ $index }})"
                                                class="thumbnail aspect-square rounded-lg overflow-hidden border-2 transition-all {{ $index === 0 ? 'border-blue-600' : 'border-transparent hover:border-gray-300' }}"
                                                data-index="{{ $index }}"
                                            >
                                                <img 
                                                    src="{{ $image->image_url }}" 
                                                    alt="Imagen {{ $index + 1 }}" 
                                                    class="w-full h-full object-cover"
                                                >
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @else
                            <!-- Placeholder when no images -->
                            <div class="aspect-[16/9] bg-gray-200 flex items-center justify-center">
                                <svg class="w-24 h-24 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <!-- Property Description -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('properties.description') }}</h2>
                        <div class="prose max-w-none text-gray-700 whitespace-pre-line">
                            {{ $property->description }}
                        </div>
                    </div>

                    <!-- Property Details -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('properties.characteristics') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                <span class="text-gray-600">{{ __('properties.property_type') }}</span>
                                <span class="font-semibold text-gray-900">{{ __('properties.types.' . $property->property_type) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                <span class="text-gray-600">{{ __('properties.transaction_type') }}</span>
                                <span class="font-semibold text-gray-900">{{ __('properties.transaction_types.' . $property->transaction_type) }}</span>
                            </div>
                            @if($property->conditions)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.condition') }}</span>
                                    <span class="font-semibold text-gray-900">{{ ucfirst($property->conditions) }}</span>
                                </div>
                            @endif
                            @if($property->bedrooms)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.features.bedrooms') }}</span>
                                    <span class="font-semibold text-gray-900">{{ $property->bedrooms }}</span>
                                </div>
                            @endif
                            @if($property->bathrooms)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.features.bathrooms') }}</span>
                                    <span class="font-semibold text-gray-900">{{ $property->bathrooms }}</span>
                                </div>
                            @endif
                            @if($property->parking_spaces)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.features.garages') }}</span>
                                    <span class="font-semibold text-gray-900">{{ $property->parking_spaces }}</span>
                                </div>
                            @endif
                            @if($property->area)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.features.covered_area') }}</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($property->area) }} {{ __('properties.sqm') }}</span>
                                </div>
                            @endif
                            @if($property->lotsize)
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <span class="text-gray-600">{{ __('properties.features.land_area') }}</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($property->lotsize) }} {{ __('properties.sqm') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('properties.location') }}</h2>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mt-1 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $property->address }}</p>
                                    <p class="text-gray-600">{{ $property->city }}, {{ $property->state }}</p>
                                    <p class="text-gray-600">{{ $property->country }} @if($property->postal_code) - CP: {{ $property->postal_code }} @endif</p>
                                </div>
                            </div>
                            
                            @if($property->latitude && $property->longitude)
                                <!-- OpenStreetMap with Leaflet -->
                                <div class="mt-4">
                                    <div id="propertyMap" class="h-80 rounded-lg shadow-md z-0"></div>
                                    <p class="text-xs text-gray-500 mt-2 text-center">
                                        Coordenadas: {{ number_format($property->latitude, 6) }}, {{ number_format($property->longitude, 6) }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <!-- Contact Form -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6 sticky top-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('properties.contact_advertiser') }}</h3>
                        
                        <!-- Agent Info -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <div class="flex items-center mb-4">
                                <div class="w-16 h-16 bg-gray-300 rounded-full flex items-center justify-center text-gray-600 font-semibold text-xl">
                                    {{ strtoupper(substr($property->user->name, 0, 2)) }}
                                </div>
                                <div class="ml-4">
                                    <div class="font-semibold text-gray-900">{{ $property->user->name }}</div>
                                    @if($property->user->agency)
                                        <div class="text-sm text-gray-600">{{ $property->user->agency }}</div>
                                    @endif
                                    @if($property->user->email)
                                        <div class="text-sm text-gray-600">{{ $property->user->email }}</div>
                                    @endif
                                </div>
                            </div>

                            <!-- WhatsApp Button - Prominente después de la info del usuario -->
                            @if($property->user->movil)
                                <a 
                                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $property->user->movil) }}?text={{ urlencode(__('properties.whatsapp_message', ['property' => $property->title])) }}" 
                                    target="_blank"
                                    class="flex items-center justify-center w-full bg-[#128C7E] text-white py-3 px-6 rounded-lg hover:bg-[#075E54] transition-colors font-semibold shadow-md hover:shadow-lg"
                                >
                                    <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    {{ __('properties.whatsapp_contact') }}
                                </a>
                            @endif
                        </div>

                        <!-- Success/Error Messages -->
                        @if(session('success'))
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                                <ul class="list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Contact Form - OCULTO -->
                        {{-- @auth
                            @if($property->user_id !== auth()->id())
                                <form action="/{{ $locale }}/{{ $countrySlug }}/{{ $citySlug }}/propiedad/{{ $property->id }}/message" method="POST" class="space-y-4">
                                    @csrf
                                    
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('properties.your_name') }} *</label>
                                        <input 
                                            type="text" 
                                            id="name" 
                                            name="name" 
                                            required
                                            value="{{ old('name', auth()->user()->name) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="{{ __('properties.your_name') }}"
                                        >
                                    </div>

                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('properties.your_email') }} *</label>
                                        <input 
                                            type="email" 
                                            id="email" 
                                            name="email" 
                                            required
                                            value="{{ old('email', auth()->user()->email) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="tu@email.com"
                                        >
                                    </div>

                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('properties.your_phone') }}</label>
                                        <input 
                                            type="tel" 
                                            id="phone" 
                                            name="phone"
                                            value="{{ old('phone', auth()->user()->movil) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="{{ __('properties.your_phone') }}"
                                        >
                                    </div>

                                    <div>
                                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">{{ __('properties.your_message') }} *</label>
                                        <textarea 
                                            id="message" 
                                            name="message" 
                                            rows="4"
                                            required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="{{ __('properties.message_placeholder') }}"
                                        >{{ old('message', __('properties.default_message', ['property' => $property->title])) }}</textarea>
                                    </div>

                                    <button 
                                        type="submit"
                                        class="w-full bg-blue-600 text-white py-3 px-6 rounded-md hover:bg-blue-700 transition-colors font-semibold"
                                    >
                                        {{ __('properties.send_inquiry') }}
                                    </button>
                                </form>
                            @else
                                <div class="p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-center">
                                    <p>{{ __('properties.your_property') }}</p>
                                </div>
                            @endif
                        @else
                            <div class="p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg text-center">
                                <p class="mb-3">{{ __('messages.auth.login_required') }}</p>
                                <a href="{{ route('login') }}" class="inline-block bg-blue-600 text-white py-2 px-6 rounded-md hover:bg-blue-700 transition-colors font-semibold">
                                    {{ __('messages.login') }}
                                </a>
                            </div>
                        @endauth --}}

                        <!-- Call Button (solo si tiene móvil) -->
                        @if($property->user->movil)
                            <div class="mt-3 pt-2">
                                <a 
                                    href="tel:{{ $property->user->movil }}" 
                                    class="flex items-center justify-center w-full bg-green-600 text-white py-3 px-6 rounded-md hover:bg-green-700 transition-colors font-semibold"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                    </svg>
                                    {{ __('properties.call_now') }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Share -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('messages.share') }}</h3>
                        <div class="flex gap-2">
                            <button 
                                onclick="shareOnFacebook()"
                                class="flex-1 bg-[#1877F2] text-white py-2 px-4 rounded-md hover:bg-[#166FE5] transition-colors text-sm font-medium"
                            >
                                Facebook
                            </button>
                            <button 
                                onclick="shareOnTwitter()"
                                class="flex-1 bg-[#1DA1F2] text-white py-2 px-4 rounded-md hover:bg-[#1A91DA] transition-colors text-sm font-medium"
                            >
                                Twitter
                            </button>
                            <button 
                                onclick="copyLink()"
                                class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 transition-colors text-sm font-medium"
                            >
                                {{ __('messages.copy_link') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Properties -->
            @if($relatedProperties->count() > 0)
                <div class="mt-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ __('properties.related_properties') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach($relatedProperties as $related)
                            @php
                                $relatedCountrySlug = Str::slug($related->country);
                                $relatedCitySlug = Str::slug($related->city);
                                $relatedTitleSlug = Str::slug($related->title);
                                $relatedUrl = "/{$locale}/{$relatedCountrySlug}/{$relatedCitySlug}/propiedad/{$related->id}-{$relatedTitleSlug}";
                            @endphp
                            <a href="{{ $relatedUrl }}" class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                <!-- Property Image -->
                                <div class="relative">
                                    @if($related->primaryImage)
                                        <img 
                                            src="{{ $related->primaryImage->image_url }}" 
                                            alt="{{ $related->title }}" 
                                            class="w-full h-48 object-cover"
                                        >
                                    @else
                                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    @endif
                                    
                                    <div class="absolute top-3 left-3">
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-blue-600 rounded">
                                            {{ ucfirst($related->transaction_type) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Property Info -->
                                <div class="p-4">
                                    <div class="mb-2">
                                        <span class="text-xl font-bold text-green-600">
                                            {{ $related->currency }} {{ number_format($related->price) }}
                                        </span>
                                    </div>
                                    
                                    <h3 class="text-base font-semibold text-gray-900 mb-2 line-clamp-2">
                                        {{ $related->title }}
                                    </h3>
                                    
                                    <p class="text-sm text-gray-600 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $related->city }}, {{ $related->country }}
                                    </p>
                                    
                                    <div class="flex gap-4 text-sm text-gray-600">
                                        @if($related->bedrooms)
                                            <span>{{ $related->bedrooms }} hab.</span>
                                        @endif
                                        @if($related->bathrooms)
                                            <span>{{ $related->bathrooms }} baños</span>
                                        @endif
                                        @if($related->area)
                                            <span>{{ number_format($related->area) }} m²</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

    <!-- Image Gallery Script -->
    <script>
        const images = @json($property->images->pluck('image_url'));
        let currentImageIndex = 0;

        function showImage(index) {
            currentImageIndex = index;
            document.getElementById('mainImage').src = images[index];
            document.getElementById('currentImageIndex').textContent = index + 1;
            
            // Update thumbnail borders
            document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
                if (i === index) {
                    thumb.classList.remove('border-transparent');
                    thumb.classList.add('border-blue-600');
                } else {
                    thumb.classList.remove('border-blue-600');
                    thumb.classList.add('border-transparent');
                }
            });
        }

        function nextImage() {
            currentImageIndex = (currentImageIndex + 1) % images.length;
            showImage(currentImageIndex);
        }

        function previousImage() {
            currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
            showImage(currentImageIndex);
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                previousImage();
            } else if (e.key === 'ArrowRight') {
                nextImage();
            }
        });

        // Share functions
        function shareOnFacebook() {
            const url = encodeURIComponent(window.location.href);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
        }

        function shareOnTwitter() {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent('{{ $property->title }}');
            window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
        }

        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('¡Enlace copiado al portapapeles!');
            });
        }

        // Initialize Leaflet Map
        @if($property->latitude && $property->longitude)
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for the DOM to fully render
            setTimeout(function() {
                // Initialize the map
                const map = L.map('propertyMap').setView([{{ $property->latitude }}, {{ $property->longitude }}], 15);

                // Add OpenStreetMap tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19,
                }).addTo(map);

                // Custom marker icon (house icon)
                const propertyIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div style="position: relative;">
                            <div style="
                                background-color: #2563eb;
                                width: 40px;
                                height: 40px;
                                border-radius: 50% 50% 50% 0;
                                transform: rotate(-45deg);
                                border: 3px solid white;
                                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                            "></div>
                            <div style="
                                position: absolute;
                                top: 8px;
                                left: 8px;
                                transform: rotate(45deg);
                                color: white;
                                font-size: 18px;
                            ">🏠</div>
                        </div>
                    `,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40],
                    popupAnchor: [0, -40]
                });

                // Add marker
                const marker = L.marker([{{ $property->latitude }}, {{ $property->longitude }}], {
                    icon: propertyIcon
                }).addTo(map);

                // Add circle to show approximate area
                L.circle([{{ $property->latitude }}, {{ $property->longitude }}], {
                    color: '#2563eb',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.1,
                    radius: 100 // 100 meters radius
                }).addTo(map);

                // Add scale control
                L.control.scale({
                    imperial: false,
                    metric: true
                }).addTo(map);

                // Optional: Add a "View on OpenStreetMap" link
                const osmLink = L.control({position: 'bottomright'});
                osmLink.onAdd = function(map) {
                    const div = L.DomUtil.create('div', 'leaflet-control-attribution leaflet-control');
                    div.innerHTML = `
                        <a href="https://www.openstreetmap.org/?mlat={{ $property->latitude }}&mlon={{ $property->longitude }}#map=17/{{ $property->latitude }}/{{ $property->longitude }}" 
                           target="_blank" 
                           style="font-size: 11px; color: #0078A8; text-decoration: none;">
                            Ver en OpenStreetMap ↗
                        </a>
                    `;
                    return div;
                };
                osmLink.addTo(map);

                // Force the map to recalculate its size and center
                setTimeout(function() {
                    map.invalidateSize();
                    map.setView([{{ $property->latitude }}, {{ $property->longitude }}], 15);
                }, 100);
            }, 100);
        });
        @endif
    </script>

</x-layouts.marketing>
