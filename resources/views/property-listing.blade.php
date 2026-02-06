@php
    // El SEO ya viene completo del controlador
@endphp

<x-layouts.marketing :seo="$seo">
    
    {{-- Hero Section --}}
    <div class="bg-white border-b py-6">
        <div class="container mx-auto px-4">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    {{ $seo['title'] }}
                </h1>
                <p class="text-base text-gray-600">
                    {{ trans_choice('properties.results.found', $properties->total(), ['count' => $properties->total()]) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Breadcrumbs --}}
    <div class="bg-gray-50 border-b">
        <div class="container mx-auto px-4 py-3">
            <nav aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1 md:gap-3">
                    @foreach($breadcrumbs as $index => $breadcrumb)
                        <li class="inline-flex items-center">
                            @if($index > 0)
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                            @if($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                    {{ $breadcrumb['label'] }}
                                </a>
                            @else
                                <span class="text-sm font-medium text-gray-500">
                                    {{ $breadcrumb['label'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            
            {{-- Sidebar Filters --}}
            <aside class="lg:w-1/4">
                {{-- Botón Toggle para Móviles --}}
                <button 
                    type="button"
                    onclick="toggleFilters()"
                    id="filter-toggle-btn"
                    class="lg:hidden w-full mb-4 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg flex items-center justify-between transition duration-200"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        {{ __('properties.filters') }}
                    </span>
                    <svg id="filter-arrow" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <script>
                    function toggleFilters() {
                        const panel = document.getElementById('filters-panel');
                        const arrow = document.getElementById('filter-arrow');
                        panel.classList.toggle('hidden');
                        arrow.classList.toggle('rotate-180');
                    }
                </script>

                {{-- Panel de Filtros (Oculto por defecto en móviles) --}}
                <div id="filters-panel" class="hidden lg:block bg-white rounded-lg shadow-md p-6 lg:sticky lg:top-4">
                    <h3 class="hidden lg:flex text-lg font-bold mb-4 items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        {{ __('properties.filters') }}
                    </h3>

                    <form method="GET" action="{{ url()->current() }}" class="space-y-6">
                        
                        {{-- Ordenamiento --}}
                        <div>
                            <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.sort_by') }}
                            </label>
                            <select name="sort" id="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                                <option value="featured" {{ request('sort') === 'featured' ? 'selected' : '' }}>{{ __('properties.sort.featured') }}</option>
                                <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>{{ __('properties.sort.newest') }}</option>
                                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>{{ __('properties.sort.oldest') }}</option>
                                <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>{{ __('properties.sort.price_asc') }}</option>
                                <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>{{ __('properties.sort.price_desc') }}</option>
                                <option value="area_asc" {{ request('sort') === 'area_asc' ? 'selected' : '' }}>{{ __('properties.sort.area_asc') }}</option>
                                <option value="area_desc" {{ request('sort') === 'area_desc' ? 'selected' : '' }}>{{ __('properties.sort.area_desc') }}</option>
                            </select>
                        </div>

                        {{-- Precio --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.filters_label.price_range') }}
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="min_price" placeholder="{{ __('properties.filters_label.min_price') }}" value="{{ request('min_price') }}" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0">
                                <input type="number" name="max_price" placeholder="{{ __('properties.filters_label.max_price') }}" value="{{ request('max_price') }}" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0">
                            </div>
                        </div>

                        {{-- Habitaciones --}}
                        <div>
                            <label for="min_bedrooms" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.filters_label.min_bedrooms') }}
                            </label>
                            <input type="number" name="min_bedrooms" id="min_bedrooms" value="{{ request('min_bedrooms') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0" max="{{ $filterOptions['max_bedrooms'] }}">
                        </div>

                        {{-- Baños --}}
                        <div>
                            <label for="min_bathrooms" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.filters_label.min_bathrooms') }}
                            </label>
                            <input type="number" name="min_bathrooms" id="min_bathrooms" value="{{ request('min_bathrooms') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0" max="{{ $filterOptions['max_bathrooms'] }}">
                        </div>

                        {{-- Área --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.filters_label.min_area') }}
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="min_area" placeholder="Min" value="{{ request('min_area') }}" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0">
                                <input type="number" name="max_area" placeholder="Max" value="{{ request('max_area') }}" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0">
                            </div>
                        </div>

                        {{-- Cocheras --}}
                        <div>
                            <label for="min_parking" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('properties.parking_spaces') }}
                            </label>
                            <input type="number" name="min_parking" id="min_parking" value="{{ request('min_parking') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" min="0" max="{{ $filterOptions['max_parking'] }}">
                        </div>

                        {{-- Botones --}}
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                                {{ __('properties.apply_filters') }}
                            </button>
                            <a href="{{ url()->current() }}" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md text-center transition duration-200">
                                {{ __('properties.clear_all_filters') }}
                            </a>
                        </div>
                    </form>
                </div>
            </aside>

            {{-- Listings Grid --}}
            <main class="lg:w-3/4">
                
                @if($properties->isEmpty())
                    {{-- No Results --}}
                    <div class="bg-white rounded-lg shadow-md p-12 text-center">
                        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">
                            {{ __('properties.results.no_results_title') }}
                        </h3>
                        <p class="text-gray-600 mb-4">
                            {{ __('properties.results.no_results_message') }}
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ __('properties.results.try_adjusting') }}
                        </p>
                    </div>
                @else
                    {{-- Results Info --}}
                    <div class="mb-6 flex justify-between items-center">
                        <p class="text-gray-600">
                            {{ __('properties.results.showing', [
                                'from' => $properties->firstItem(),
                                'to' => $properties->lastItem(),
                                'total' => $properties->total()
                            ]) }}
                        </p>
                    </div>

                    {{-- Properties Grid --}}
                    <div class="grid md:grid-cols-2 gap-6">
                        @foreach($properties as $property)
                            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                {{-- Image --}}
                                <div class="relative h-48 bg-gray-200">
                                    @if($property->primaryImage)
                                        <img src="{{ Storage::url($property->primaryImage->image_path) }}" 
                                             alt="{{ $property->title }}" 
                                             loading="lazy"
                                             class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                            </svg>
                                        </div>
                                    @endif
                                    
                                    {{-- Featured Badge --}}
                                    @if($property->is_featured)
                                        <span class="absolute top-2 right-2 bg-yellow-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                                            {{ __('properties.search_results.featured') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="p-4">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-1">
                                        {{ $property->title }}
                                    </h3>
                                    
                                    <p class="text-2xl font-bold text-blue-600 mb-3">
                                        {{ $property->currency }} {{ number_format($property->price, 0, ',', '.') }}
                                    </p>

                                    <p class="text-sm text-gray-600 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        {{ $property->city }}, {{ $property->state }}, {{ $property->country }}
                                    </p>

                                    <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
                                        @if($property->bedrooms)
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                                </svg>
                                                {{ $property->bedrooms }}
                                            </span>
                                        @endif
                                        @if($property->bathrooms)
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
                                                </svg>
                                                {{ $property->bathrooms }}
                                            </span>
                                        @endif
                                        @if($property->area)
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                                                </svg>
                                                {{ number_format($property->area, 0) }} m²
                                            </span>
                                        @endif
                                    </div>

                                    @php
                                        $countrySlug = Str::slug($property->country);
                                        $citySlug = Str::slug($property->city);
                                        $titleSlug = Str::slug($property->title);
                                    @endphp
                                    
                                    <a href="/{{ $locale }}/{{ $countrySlug }}/{{ $citySlug }}/propiedad/{{ $property->id }}-{{ $titleSlug }}" 
                                       class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-medium py-2 rounded-md transition duration-200">
                                        {{ __('properties.view_details') }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $properties->links() }}
                    </div>
                @endif
            </main>
        </div>
    </div>

</x-layouts.marketing>
