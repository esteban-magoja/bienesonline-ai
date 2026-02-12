@php
    // El SEO ya viene completo del controlador
@endphp

<x-layouts.marketing :seo="$seo">
    
    {{-- Hero Section con Info del Usuario --}}
    <div class="bg-gray-100 py-12">
        <div class="container mx-auto px-4">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    
                    {{-- Avatar --}}
                    <div class="flex-shrink-0">
                        @if($user->avatar)
                            <img src="{{ Storage::url($user->avatar) }}" 
                                 alt="{{ $user->name }}" 
                                 class="w-32 h-32 rounded-full border-4 border-white shadow-xl object-cover">
                        @else
                            <div class="w-32 h-32 rounded-full border-4 border-white shadow-xl bg-white flex items-center justify-center">
                                <svg class="w-20 h-20 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Info del Usuario --}}
                    <div class="flex-1 text-center md:text-left">
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">
                            {{ $user->agency ?: $user->name }}
                        </h1>
                        
                        @if($user->agency && $user->name)
                            <p class="text-xl text-gray-600 mb-4">{{ $user->name }}</p>
                        @endif

                        {{-- Ubicación --}}
                        @if($user->city || $user->state || $user->country)
                            <p class="text-gray-600 flex items-center justify-center md:justify-start mb-4">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                {{ collect([$user->city, $user->state, $user->country])->filter()->join(', ') }}
                            </p>
                        @endif

                        {{-- Estadísticas --}}
                        <div class="flex flex-wrap gap-4 justify-center md:justify-start">
                            <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 shadow-sm">
                                <div class="text-2xl font-bold text-gray-900">{{ $stats['total_active'] }}</div>
                                <div class="text-sm text-gray-600">{{ __('properties.user_profile.active_properties') }}</div>
                            </div>
                            @if($stats['total_sales'] > 0)
                                <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 shadow-sm">
                                    <div class="text-2xl font-bold text-gray-900">{{ $stats['total_sales'] }}</div>
                                    <div class="text-sm text-gray-600">{{ __('properties.user_profile.properties_for_sale') }}</div>
                                </div>
                            @endif
                            @if($stats['total_rentals'] > 0)
                                <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 shadow-sm">
                                    <div class="text-2xl font-bold text-gray-900">{{ $stats['total_rentals'] }}</div>
                                    <div class="text-sm text-gray-600">{{ __('properties.user_profile.properties_for_rent') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Info de Contacto (Sidebar en desktop) --}}
                    <div class="bg-white rounded-lg shadow-xl p-6 w-full md:w-80">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            {{ __('properties.user_profile.contact_info') }}
                        </h3>

                        <div class="space-y-3">
                            {{-- Email --}}
                            @if($user->email)
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-500 mb-1">{{ __('properties.user_profile.email') }}</p>
                                        <a href="mailto:{{ $user->email }}" class="text-sm text-blue-600 hover:text-blue-800 break-all">
                                            {{ $user->email }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            {{-- Teléfono/Móvil --}}
                            @if($user->movil)
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-500 mb-1">{{ __('properties.user_profile.phone') }}</p>
                                        <p class="text-sm text-gray-900 font-medium">{{ $user->movil }}</p>
                                    </div>
                                </div>

                                {{-- Botones de Acción --}}
                                <div class="space-y-2 pt-2">
                                    {{-- WhatsApp --}}
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $user->movil) }}" 
                                       target="_blank" 
                                       class="w-full flex items-center justify-center bg-[#25D366] hover:bg-[#128C7E] text-white font-medium py-3 px-4 rounded-lg transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        {{ __('properties.user_profile.send_whatsapp') }}
                                    </a>

                                    {{-- Llamar --}}
                                    <a href="tel:{{ $user->movil }}" 
                                       class="w-full flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-900 font-medium py-3 px-4 rounded-lg transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        {{ __('properties.user_profile.call_now') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
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
        @if($properties->isEmpty())
            {{-- No Properties --}}
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">
                    {{ __('properties.results.no_results_title') }}
                </h3>
                <p class="text-gray-600">
                    {{ __('properties.user_profile.no_properties') }}
                </p>
            </div>
        @else
            {{-- Filtros y Ordenamiento (Sticky Bar) --}}
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap gap-4 items-end">
                    
                    {{-- Tipo de Operación --}}
                    <div class="flex-1 min-w-[200px]">
                        <label for="transaction_type" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('properties.filters_label.transaction_type') }}
                        </label>
                        <select name="transaction_type" id="transaction_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __('properties.all') }}</option>
                            <option value="sale" {{ request('transaction_type') == 'sale' ? 'selected' : '' }}>{{ __('properties.transaction_types.sale') }}</option>
                            <option value="rent" {{ request('transaction_type') == 'rent' ? 'selected' : '' }}>{{ __('properties.transaction_types.rent') }}</option>
                        </select>
                    </div>

                    {{-- Tipo de Propiedad --}}
                    <div class="flex-1 min-w-[200px]">
                        <label for="property_type" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('properties.filters_label.property_type') }}
                        </label>
                        <select name="property_type" id="property_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __('properties.all') }}</option>
                            @foreach(['house', 'apartment', 'office', 'commercial', 'land', 'field', 'warehouse'] as $type)
                                <option value="{{ $type }}" {{ request('property_type') == $type ? 'selected' : '' }}>
                                    {{ __("properties.types.{$type}") }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Ordenamiento --}}
                    <div class="flex-1 min-w-[200px]">
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('properties.sort_by') }}
                        </label>
                        <select name="sort" id="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="created_at" {{ request('sort', 'created_at') == 'created_at' ? 'selected' : '' }}>{{ __('properties.sort.newest') }}</option>
                            <option value="price" {{ request('sort') == 'price' ? 'selected' : '' }}>{{ __('properties.sort.price_asc') }}</option>
                            <option value="area" {{ request('sort') == 'area' ? 'selected' : '' }}>{{ __('properties.sort.area_desc') }}</option>
                        </select>
                    </div>

                    {{-- Botones --}}
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-200">
                            {{ __('properties.apply_filters') }}
                        </button>
                        <a href="{{ url()->current() }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-200">
                            {{ __('properties.clear_all_filters') }}
                        </a>
                    </div>
                </form>
            </div>

            {{-- Results Info --}}
            <div class="mb-6">
                <p class="text-gray-600">
                    {{ __('properties.results.showing', [
                        'from' => $properties->firstItem(),
                        'to' => $properties->lastItem(),
                        'total' => $properties->total()
                    ]) }}
                </p>
            </div>

            {{-- Properties Grid --}}
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                            
                            {{-- Transaction Type Badge --}}
                            <span class="absolute top-2 left-2 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                {{ __("properties.transaction_types.{$property->transaction_type}") }}
                            </span>
                        </div>

                        {{-- Content --}}
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2">
                                {{ $property->title }}
                            </h3>
                            
                            <p class="text-2xl font-bold text-blue-600 mb-3">
                                {{ $property->currency }} {{ number_format($property->price, 0, ',', '.') }}
                            </p>

                            <p class="text-sm text-gray-600 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="line-clamp-1">{{ $property->city }}, {{ $property->state }}</span>
                            </p>

                            <div class="flex items-center gap-3 text-sm text-gray-600 mb-4">
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
                                        {{ number_format($property->area, 0, ',', '.') }} m²
                                    </span>
                                @endif
                            </div>

                            @php
                                $seoService = app(\App\Services\SeoService::class);
                                $propertyUrl = $seoService->generatePropertyUrl($property, app()->getLocale());
                            @endphp

                            <a href="{{ $propertyUrl }}" 
                               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
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
    </div>

</x-layouts.marketing>
