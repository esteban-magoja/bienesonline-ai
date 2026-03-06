@php
    $heroCountries = \App\Models\PropertyListing::distinct('country')
        ->whereNotNull('country')
        ->where('country', '!=', '')
        ->where('is_active', true)
        ->orderBy('country')
        ->pluck('country');
@endphp

<section class="flex relative top-0 flex-col justify-center items-center -mt-24 w-full  bg-white pb-5">
    <div class="flex flex-col flex-1 gap-6 justify-between items-center px-8 pt-32 mx-auto w-full max-w-2xl text-center md:px-12 xl:px-20 lg:pt-32 lg:pb-16 lg:max-w-7xl lg:flex-row">
        <div class="w-full">
            <h1 class="text-6xl font-bold tracking-tighter text-center sm:text-7xl md:text-[84px] text-zinc-900 text-balance">
                {{ __('messages.home_page.hero_title_1') }} <span class="text-transparent bg-clip-text bg-gradient-to-b from-neutral-900 to-neutral-500">{{ __('messages.home_page.hero_title_2') }}</span>
            </h1>
            <p class="mx-auto mt-5 text-lg font-normal text-center md:text-xl max-w-2xl text-zinc-500">
                {{ __('messages.home_page.hero_subtitle') }}
            </p>

            {{-- Search Box --}}
            <div class="mx-auto mt-8 max-w-3xl">
                <div class="bg-white rounded-xl shadow-lg border border-zinc-200 p-4 sm:p-6">
                    <form method="GET" action="{{ route_localized('property.search') }}" class="flex flex-col sm:flex-row gap-3">
                        {{-- Country --}}
                        <div class="flex-shrink-0 sm:w-44">
                            <select name="country" required class="w-full px-3 py-3 border border-zinc-300 rounded-lg text-sm focus:ring-2 focus:ring-zinc-900 focus:border-zinc-900 transition-colors duration-200 bg-white text-zinc-700">
                                <option value="">{{ __('properties.search_form.select_country') }}</option>
                                @foreach($heroCountries as $country)
                                    <option value="{{ $country }}">{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Search input --}}
                        <div class="flex-1 relative">
                            <input
                                type="text"
                                name="search"
                                required
                                minlength="5"
                                placeholder="{{ __('properties.search_form.placeholder') }}"
                                class="w-full px-4 py-3 border border-zinc-300 rounded-lg text-sm focus:ring-2 focus:ring-zinc-900 focus:border-zinc-900 transition-colors duration-200"
                            >
                        </div>

                        {{-- Submit --}}
                        <button type="submit" class="flex-shrink-0 inline-flex items-center justify-center px-6 py-3 text-sm font-medium text-white bg-zinc-900 rounded-lg hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-900 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            {{ __('properties.search_form.search_button') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="flex flex-col gap-3 justify-center items-center mx-auto mt-6 md:gap-2 md:flex-row">
                @guest
                <x-button size="lg" color="secondary" class="w-full md:w-auto" href="/join_us" tag="a">{{ __('messages.home_page.add_property') }}</x-button>
                <x-button size="lg" color="secondary" class="w-full md:w-auto" href="{{ route_localized('requests.create') }}" tag="a">{{ __('messages.publish_request') }}</x-button>
                @else
                <x-button size="lg" color="secondary" class="w-full md:w-auto" href="/property-listings/create" tag="a">{{ __('messages.home_page.add_property') }}</x-button>
                <x-button size="lg" color="secondary" class="w-full md:w-auto" href="{{ route_localized('dashboard.requests.create') }}" tag="a">{{ __('messages.publish_request') }}</x-button>
                @endguest
            </div>
        </div>
    </div>
</section>