<x-layouts.marketing :seo="$seo">
    <section class="border-b border-zinc-200 bg-zinc-50 py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav aria-label="Breadcrumb" class="mb-5">
                <ol class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                    @foreach($breadcrumbs as $breadcrumb)
                        <li class="flex items-center gap-2">
                            @if(!$loop->first)
                                <span aria-hidden="true">/</span>
                            @endif

                            @if($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}" class="hover:text-zinc-900 hover:underline">
                                    {{ $breadcrumb['label'] }}
                                </a>
                            @else
                                <span>{{ $breadcrumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">
                {{ __('properties.search_results.title') }}
            </p>
            <h1 class="max-w-4xl text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                {{ Str::title($searchTerm) }}
            </h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-zinc-600">
                {{ trans_choice('properties.results.found', $properties->total(), ['count' => $properties->total()]) }}
                {{ __('properties.search_results.in_country', ['country' => $country]) }}
            </p>
        </div>
    </section>

    <section class="bg-white py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if($properties->isEmpty())
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-6 py-14 text-center">
                    <h2 class="text-xl font-semibold text-zinc-900">
                        {{ __('properties.results.no_results_title') }}
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-zinc-600">
                        {{ __('properties.results.try_adjusting') }}
                    </p>
                    <a href="{{ route_localized('property.search') }}" class="mt-6 inline-flex rounded-lg bg-zinc-900 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-700">
                        {{ __('properties.search_properties') }}
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($properties as $property)
                        <x-property-search-card :property="$property" :locale="$locale" />
                    @endforeach
                </div>

                @if($properties->hasPages())
                    <nav aria-label="{{ __('pagination.previous') }}" class="mt-10">
                        {{ $properties->links() }}
                    </nav>
                @endif
            @endif
        </div>
    </section>
</x-layouts.marketing>
