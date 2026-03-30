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
                <a
                    href="{{ url('/' . app()->getLocale() . '/' . \App\Helpers\PropertySlugHelper::normalize($country)) }}"
                    class="group flex items-center justify-between rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-900 hover:bg-zinc-50 hover:text-zinc-900"
                >
                    <span>{{ $country }}</span>
                    <svg class="h-4 w-4 text-zinc-400 transition group-hover:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
