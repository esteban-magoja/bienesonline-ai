<x-layouts.marketing :seo="$seo">
    <div class="bg-gray-100 py-12">
        <div class="container mx-auto px-4">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-4xl font-bold text-gray-900 mb-3">
                    {{ __('properties.agents_directory.directory') }}
                </h1>
                <p class="text-gray-600 text-lg">
                    @if($locationLabel)
                        {{ __('properties.agents_directory.description_with_location', ['location' => $locationLabel]) }}
                    @else
                        {{ __('properties.agents_directory.description') }}
                    @endif
                </p>
            </div>
        </div>
    </div>

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

    <div class="container mx-auto px-4 py-8">
        @if($locationLinks)
            @php
                $locationHeading = match ($locationLevel) {
                    'cities' => __('properties.agents_directory.browse_cities'),
                    'states' => __('properties.agents_directory.browse_states'),
                    default => __('properties.agents_directory.browse_countries'),
                };
            @endphp

            <section class="mb-8">
                <details class="group md:hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 font-semibold text-gray-900">
                        <span>{{ $locationHeading }}</span>
                        <svg class="h-5 w-5 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </summary>
                    <div class="grid grid-cols-1 gap-3 border-t border-gray-200 p-3">
                        @foreach($locationLinks as $location)
                            <a href="{{ $location['url'] }}" class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-gray-900 transition hover:border-blue-400 hover:text-blue-700">
                                <span class="font-medium">{{ $location['label'] }}</span>
                                <span class="shrink-0 text-sm text-gray-500">
                                    {{ trans_choice('properties.agents_directory.listings_count', $location['listings_count'], ['count' => $location['listings_count']]) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </details>

                <div class="hidden md:block">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $locationHeading }}</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($locationLinks as $location)
                            <a href="{{ $location['url'] }}" class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-900 shadow-sm transition hover:border-blue-400 hover:text-blue-700">
                                <span class="font-medium">{{ $location['label'] }}</span>
                                <span class="shrink-0 text-sm text-gray-500">
                                    {{ trans_choice('properties.agents_directory.listings_count', $location['listings_count'], ['count' => $location['listings_count']]) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($agents->isEmpty())
            <div class="bg-white rounded-lg shadow-md p-10 text-center">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    {{ __('properties.agents_directory.no_results_title') }}
                </h2>
                <p class="text-gray-600">{{ __('properties.agents_directory.no_results_message') }}</p>
            </div>
        @else
            <div class="mb-6 text-gray-600">
                {{ __('properties.agents_directory.results', [
                    'from' => $agents->firstItem(),
                    'to' => $agents->lastItem(),
                    'total' => $agents->total(),
                ]) }}
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($agents as $agent)
                    @php
                        $profileUrl = route($profileRouteName, ['locale' => app()->getLocale(), 'username' => $agent->username]);
                        $displayName = $agent->agency ?: $agent->name;
                        $location = $locationLabel ?: collect([$agent->city, $agent->state, $agent->country])->filter()->join(', ');
                    @endphp

                    <article class="bg-white rounded-lg shadow-md p-6 flex flex-col gap-4">
                        <div class="flex items-center gap-4">
                            @if($agent->avatar)
                                <img src="{{ $agent->avatar() }}" alt="{{ $displayName }}" class="w-14 h-14 rounded-full object-cover">
                            @else
                                <div class="w-14 h-14 rounded-full bg-gray-200"></div>
                            @endif
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ $displayName }}</h2>
                                @if($agent->name && $agent->agency)
                                    <p class="text-sm text-gray-500">{{ $agent->name }}</p>
                                @endif
                            </div>
                        </div>

                        @if($location)
                            <p class="text-sm text-gray-600">{{ $location }}</p>
                        @endif

                        <p class="text-sm font-medium text-blue-700">
                            {{ trans_choice('properties.agents_directory.listings_count', $agent->active_listings_count, ['count' => $agent->active_listings_count]) }}
                        </p>

                        <a href="{{ $profileUrl }}" class="mt-auto inline-flex justify-center items-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            {{ __('properties.agents_directory.view_profile') }}
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $agents->links() }}
            </div>
        @endif
    </div>
</x-layouts.marketing>
