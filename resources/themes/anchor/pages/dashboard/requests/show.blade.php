<x-layouts.app>
    <x-app.container class="lg:space-y-6">
        
        <x-app.heading
            :title="__('dashboard.request_detail.title')"
            :description="__('dashboard.request_detail.description')"
            :border="false"
        >
            <x-slot name="actions">
                <a href="{{ route('dashboard.requests.edit', $propertyRequest) }}" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                    {{ __('dashboard.requests.edit') }}
                </a>
                <a href="{{ route('dashboard.requests.index') }}" 
                   class="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                    {{ __('dashboard.request_form.back') }}
                </a>
            </x-slot>
        </x-app.heading>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <!-- Request Details Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $propertyRequest->title }}</h2>
                    <div class="flex items-center gap-2">
                        @if($propertyRequest->is_active)
                            @if($propertyRequest->isExpired())
                                <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">
                                    {{ __('dashboard.requests.expired') }}
                                </span>
                            @else
                                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                                    {{ __('dashboard.requests.active') }}
                                </span>
                            @endif
                        @else
                            <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">
                                {{ __('dashboard.requests.inactive') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <p class="text-gray-700 mb-6">{{ $propertyRequest->description }}</p>

            @if($propertyRequest->client_name || $propertyRequest->client_email || $propertyRequest->client_phone)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-semibold text-blue-900 mb-3">{{ __('dashboard.request_detail.client_data') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if($propertyRequest->client_name)
                            <div>
                                <span class="text-sm text-blue-700">{{ __('dashboard.request_detail.name') }}</span>
                                <p class="font-medium text-blue-900">{{ $propertyRequest->client_name }}</p>
                            </div>
                        @endif
                        @if($propertyRequest->client_email)
                            <div>
                                <span class="text-sm text-blue-700">{{ __('dashboard.request_detail.email') }}</span>
                                <p class="font-medium text-blue-900">
                                    <a href="mailto:{{ $propertyRequest->client_email }}" class="hover:underline">
                                        {{ $propertyRequest->client_email }}
                                    </a>
                                </p>
                            </div>
                        @endif
                        @if($propertyRequest->client_phone)
                            <div>
                                <span class="text-sm text-blue-700">{{ __('dashboard.request_detail.whatsapp') }}</span>
                                <p class="font-medium text-blue-900">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $propertyRequest->client_phone) }}" 
                                       target="_blank" 
                                       class="hover:underline inline-flex items-center gap-1">
                                        {{ $propertyRequest->client_phone }}
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                    </a>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 pb-4 border-b border-gray-200">
                <div>
                    <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.property_type') }}</span>
                    <p class="font-medium text-gray-900 capitalize">{{ $propertyRequest->property_type }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.operation') }}</span>
                    <p class="font-medium text-gray-900 capitalize">{{ $propertyRequest->transaction_type }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.budget') }}</span>
                    <p class="font-medium text-gray-900">{{ $propertyRequest->budget_range }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.location') }}</span>
                    <p class="font-medium text-gray-900">{{ $propertyRequest->city ?? $propertyRequest->state ?? $propertyRequest->country }}</p>
                </div>
            </div>

            @if($propertyRequest->min_bedrooms || $propertyRequest->min_bathrooms || $propertyRequest->min_parking_spaces || $propertyRequest->min_area)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @if($propertyRequest->min_bedrooms)
                        <div>
                            <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.min_bedrooms') }}</span>
                            <p class="font-medium text-gray-900">{{ $propertyRequest->min_bedrooms }}</p>
                        </div>
                    @endif
                    @if($propertyRequest->min_bathrooms)
                        <div>
                            <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.min_bathrooms') }}</span>
                            <p class="font-medium text-gray-900">{{ $propertyRequest->min_bathrooms }}</p>
                        </div>
                    @endif
                    @if($propertyRequest->min_parking_spaces)
                        <div>
                            <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.min_parking') }}</span>
                            <p class="font-medium text-gray-900">{{ $propertyRequest->min_parking_spaces }}</p>
                        </div>
                    @endif
                    @if($propertyRequest->min_area)
                        <div>
                            <span class="text-sm text-gray-500">{{ __('dashboard.request_detail.min_area') }}</span>
                            <p class="font-medium text-gray-900">{{ $propertyRequest->min_area }}m²</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Matches Section -->
        <div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                {{ __('dashboard.request_detail.matching_properties') }} ({{ $matches->count() }})
            </h3>

            @if($matches->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('dashboard.request_detail.no_matches') }}</h3>
                    <p class="text-gray-600">{{ __('dashboard.request_detail.no_matches_desc') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($matches as $listing)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                            <!-- Match Level Badge -->
                            <div class="p-3 border-b border-gray-200 flex justify-between items-center">
                                @if($listing->match_level === 'exact')
                                    <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                        {{ __('dashboard.matches_section.exact_match') }}
                                    </span>
                                @elseif($listing->match_level === 'semantic')
                                    <span class="px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                        {{ __('dashboard.matches_section.intelligent_match') }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                        {{ __('dashboard.matches_section.flexible_match') }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-500">{{ __('dashboard.matches_section.match_score', ['score' => $listing->match_score]) }}</span>
                            </div>

                            <!-- Property Image -->
                            <div class="relative h-48 bg-gray-200">
                                @if($listing->primaryImage)
                                    <img src="{{ $listing->primaryImage->image_url }}" 
                                         alt="{{ $listing->title }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <!-- Property Details -->
                            <div class="p-4">
                                <h4 class="font-semibold text-gray-900 mb-2 line-clamp-1">{{ $listing->title }}</h4>
                                <p class="text-lg font-bold text-blue-600 mb-2">
                                    {{ $listing->currency }} {{ number_format($listing->price, 0) }}
                                </p>
                                <p class="text-sm text-gray-600 mb-3">
                                    {{ $listing->city }}, {{ $listing->state }}
                                </p>

                                <!-- Property Features -->
                                <div class="flex gap-3 text-sm text-gray-600 mb-4">
                                    @if($listing->bedrooms)
                                        <span>{{ __('dashboard.requests.min_bedrooms_short', ['count' => $listing->bedrooms]) }}</span>
                                    @endif
                                    @if($listing->bathrooms)
                                        <span>{{ __('dashboard.requests.min_bathrooms_short', ['count' => $listing->bathrooms]) }}</span>
                                    @endif
                                    @if($listing->area)
                                        <span>{{ __('dashboard.requests.min_area_short', ['area' => $listing->area]) }}</span>
                                    @endif
                                </div>

                                <!-- Match Details -->
                                @if(!empty($listing->match_details))
                                    <div class="mb-4 p-2 bg-gray-50 rounded text-xs text-gray-600">
                                        <strong>{{ __('dashboard.matches_section.why_matches') }}:</strong>
                                        <ul class="list-disc list-inside mt-1">
                                            @foreach(array_slice($listing->match_details, 0, 3) as $detail)
                                                <li>{{ $detail }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <!-- View Button -->
                                @php
                                    $seoService = app(\App\Services\SeoService::class);
                                    $listingSlug = $seoService->generatePropertySlug($listing);
                                @endphp
                                <a href="{{ route_localized('property.show', ['id' => $listing->id, 'slug' => $listingSlug]) }}" 
                                   class="block w-full text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                    {{ __('properties.view_details') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </x-app.container>
</x-layouts.app>
