@props(['property'])

@php
    $displayImage = $property->primaryImage ?? $property->firstImage;
    $propertyUrl = app(\App\Services\SeoService::class)->generatePropertyUrl($property, app()->getLocale());
    $propertyLocation = collect([$property->city, $property->state, $property->country])->filter()->join(', ');
@endphp

<a href="{{ $propertyUrl }}" class="group block overflow-hidden rounded-xl bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
    <div class="relative h-44 bg-gray-200">
        @if($displayImage)
            <img src="{{ Storage::url($displayImage->image_path) }}" alt="{{ $property->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                <svg class="h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
        @endif

        <span class="absolute left-3 top-3 rounded-full bg-blue-600 px-3 py-1 text-xs font-bold text-white">
            {{ \App\Models\TransactionType::getLabel($property->transaction_type) }}
        </span>
    </div>

    <div class="p-4">
        <h3 class="line-clamp-2 font-bold text-gray-900 group-hover:text-blue-700">{{ $property->title }}</h3>

        <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <p class="font-semibold text-blue-600">{{ $property->currency }} {{ number_format($property->price, 0, ',', '.') }}</p>
            <p class="text-sm font-medium text-gray-500">
                {{ \App\Models\PropertyType::getLabel($property->property_type) }} - {{ \App\Models\TransactionType::getLabel($property->transaction_type) }}
            </p>
        </div>

        @if($propertyLocation)
            <p class="mt-3 flex items-start gap-1.5 text-sm text-gray-600">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 3 3 3 0 01-6 0z"></path>
                </svg>
                <span class="line-clamp-2">{{ $propertyLocation }}</span>
            </p>
        @endif

        @if($property->bedrooms || $property->bathrooms || $property->area)
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-600">
                @if($property->bedrooms)
                    <span>{{ $property->bedrooms }} {{ __('properties.features.bedrooms_short') }}</span>
                @endif
                @if($property->bathrooms)
                    <span>{{ $property->bathrooms }} {{ __('properties.features.bathrooms_short') }}</span>
                @endif
                @if($property->area)
                    <span>{{ number_format($property->area, 0, ',', '.') }} m²</span>
                @endif
            </div>
        @endif
    </div>
</a>
