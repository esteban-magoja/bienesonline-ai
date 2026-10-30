@props(['countries' => []])

@if(collect($countries)->isNotEmpty())
<section>
    <x-marketing.elements.heading
        level="h2"
        :title="__('messages.home_page.countries_title')"
        :description="__('messages.home_page.countries_description')"
    />

    <div class="mx-auto mt-10 max-w-6xl">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            @foreach($countries as $country)
                @php $countryUrl = url('/' . app()->getLocale() . '/' . \App\Helpers\PropertySlugHelper::normalize($country)); @endphp
                <div class="flex items-stretch overflow-hidden rounded-xl border border-zinc-200 bg-white text-sm font-medium text-zinc-700 transition hover:border-zinc-900">
                    <a
                        href="{{ $countryUrl }}"
                        class="group flex flex-1 items-center justify-between px-4 py-3 transition hover:bg-zinc-50 hover:text-zinc-900"
                    >
                        <span>{{ $country }}</span>
                        <svg class="h-4 w-4 text-zinc-400 transition group-hover:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a
                        href="{{ $countryUrl }}?view=map"
                        rel="nofollow"
                        title="{{ __('properties.map_view.view_on_map') }} · {{ $country }}"
                        class="flex items-center gap-1.5 border-l border-zinc-200 px-3 text-xs text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-900"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        {{ __('properties.map_view.country_map') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
