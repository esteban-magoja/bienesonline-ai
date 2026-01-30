<section class="flex relative top-0 flex-col justify-center items-center -mt-24 w-full  bg-white pb-5">
    <div class="flex flex-col flex-1 gap-6 justify-between items-center px-8 pt-32 mx-auto w-full max-w-2xl text-center md:px-12 xl:px-20 lg:pt-32 lg:pb-16 lg:max-w-7xl lg:flex-row">
        <div class="w-full">
            <h1 class="text-6xl font-bold tracking-tighter text-center sm:text-7xl md:text-[84px] text-zinc-900 text-balance">
                {{ __('messages.home_page.hero_title_1') }} <span class="text-transparent bg-clip-text bg-gradient-to-b from-neutral-900 to-neutral-500">{{ __('messages.home_page.hero_title_2') }}</span>
            </h1>
            <p class="mx-auto mt-5 text-lg font-normal text-center md:text-xl max-w-2xl text-zinc-500">
                {{ __('messages.home_page.hero_subtitle') }}
            </p>
            <div class="flex flex-col gap-3 justify-center items-center mx-auto mt-8 md:gap-2 md:flex-row">
                <x-button size="lg" class="w-full md:w-auto" href="{{ route_localized('property.search') }}" tag="a">{{ __('properties.search_properties') }}</x-button>
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