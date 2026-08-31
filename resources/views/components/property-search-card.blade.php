@php
    $displayImage = $property->primaryImage ?? $property->firstImage;
    $imageUrl = $displayImage?->image_url ?: ($displayImage?->image_path ? Storage::url($displayImage->image_path) : null);
    $propertyUrl = app(\App\Services\SeoService::class)->generatePropertyUrl($property, $locale);
    $fullPropertyTitle = $locale === 'es'
        ? $property->title
        : ($property->getTranslation('title', $locale) ?: $property->title);
    $propertyTitle = \Illuminate\Support\Str::limit($fullPropertyTitle, 100);
@endphp

<article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
    <a href="{{ $propertyUrl }}" aria-label="{{ $fullPropertyTitle }}">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $fullPropertyTitle }}" loading="lazy" class="h-48 w-full object-cover">
        @else
            <div class="flex h-48 items-center justify-center bg-zinc-100 text-sm text-zinc-500">
                {{ __('messages.no_image') }}
            </div>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <div>
            <h2 class="line-clamp-2 text-lg font-semibold text-zinc-900">
                <a href="{{ $propertyUrl }}" title="{{ $fullPropertyTitle }}" class="hover:text-indigo-600">{{ $propertyTitle }}</a>
            </h2>
            <p class="mt-1 text-sm text-zinc-500">{{ $property->city }}, {{ $property->state }}</p>
        </div>

        <p class="text-xl font-bold text-indigo-600">
            {{ $property->currency }} {{ number_format($property->price, 0, ',', '.') }}
        </p>

        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600">
            @if($property->bedrooms)
                <span>{{ $property->bedrooms }} {{ __('properties.features.bedrooms_short') }}</span>
            @endif
            @if($property->bathrooms)
                <span>{{ $property->bathrooms }} {{ __('properties.features.bathrooms_short') }}</span>
            @endif
            @if($property->area)
                <span>{{ number_format($property->area) }} m²</span>
            @endif
        </div>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium uppercase tracking-wide text-zinc-500">
                <span>
                    {{ \App\Models\PropertyType::getLabel($property->property_type, $locale) }}
                    -
                    {{ \App\Models\TransactionType::getLabel($property->transaction_type, $locale) }}
                </span>
            </div>
            @if(isset($property->similarity))
                <span class="text-xs font-semibold text-emerald-600">
                    {{ number_format($property->similarity, 0) }}% {{ __('properties.similarity') }}
                </span>
            @endif
        </div>
    </div>
</article>
