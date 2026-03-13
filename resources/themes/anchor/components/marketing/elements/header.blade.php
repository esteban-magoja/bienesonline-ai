<header 
    x-data="{ 
        mobileMenuOpen: false, 
        scrolled: false, 
        showOverlay: false, 
        topOffset: '5',
        evaluateScrollPosition(){
            if(window.pageYOffset > this.topOffset){
                this.scrolled = true;
            } else {
                this.scrolled = false;
            }
        } 
    }"
    x-init="
        window.addEventListener('resize', function() {
            if(window.innerWidth > 768) {
                mobileMenuOpen = false;
            }
        });
        $watch('mobileMenuOpen', function(value){
            if(value){ document.body.classList.add('overflow-hidden'); } else { document.body.classList.remove('overflow-hidden'); }
        });
        evaluateScrollPosition();
        window.addEventListener('scroll', function() {
            evaluateScrollPosition(); 
        })
    " 
    :class="{ 'border-zinc-800 bg-black/95 border-b backdrop-blur-lg shadow-sm' : scrolled, 'border-zinc-900 border-b bg-black translate-y-0' : !scrolled }" 
    class="box-content sticky top-0 z-50 w-full h-24" 
>
    <div 
        x-show="showOverlay"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="absolute inset-0 w-full h-screen pt-24" x-cloak>
        <div class="w-screen h-full bg-black/70"></div>
    </div>
    <x-container>
        <div class="z-30 flex items-center justify-between h-24 md:space-x-8">
            <div class="z-20 flex items-center justify-between w-full md:w-auto">
                <div class="relative z-20 inline-flex">
                    <a href="{{ route_localized('home') }}" class="flex items-center justify-center space-x-3 font-bold text-white">
                    <x-logo class="w-auto h-8 brightness-0 invert contrast-200 md:h-9"></x-logo>
                    </a>
                </div>
                <div class="flex justify-end flex-grow md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center p-2 text-zinc-200 transition duration-150 ease-in-out rounded-full hover:text-white hover:bg-white/10">
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                        <svg x-show="mobileMenuOpen" class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <nav :class="{ 'hidden' : !mobileMenuOpen, 'block md:relative absolute top-0 left-0 md:w-auto w-screen md:h-auto h-screen pointer-events-none md:z-10 z-10' : mobileMenuOpen }" class="h-full md:flex">
                <ul :class="{ 'hidden md:flex' : !mobileMenuOpen, 'flex flex-col absolute md:relative md:w-auto w-screen h-full md:h-full md:overflow-auto overflow-scroll md:pt-0 mt-24 md:pb-0 pb-48 bg-black md:bg-transparent' : mobileMenuOpen }" id="menu" class="flex items-stretch justify-start flex-1 w-full h-full ml-0 border-t border-zinc-800 pointer-events-auto md:items-center md:justify-center gap-x-8 md:w-auto md:border-t-0 md:flex-row">
                    <li class="flex-shrink-0 h-16 border-b border-zinc-800 md:border-b-0 md:h-full">
                        <a href="{{ route_localized('property.search') }}" class="flex items-center h-full text-sm font-semibold text-zinc-300 transition-all duration-200 md:px-0 px-7 hover:bg-white/5 md:hover:bg-transparent hover:text-zinc-100 md:hover:underline md:hover:decoration-2 md:hover:underline-offset-4 md:hover:decoration-zinc-500">
                            {{ __('properties.search_properties') }}
                        </a>
                    </li>
                    <li class="flex-shrink-0 h-16 border-b border-zinc-800 md:border-b-0 md:h-full">
                        <a href="/{{ app()->getLocale() }}/post-request" class="flex items-center h-full text-sm font-semibold text-zinc-300 transition-all duration-200 md:px-0 px-7 hover:bg-white/5 md:hover:bg-transparent hover:text-zinc-100 md:hover:underline md:hover:decoration-2 md:hover:underline-offset-4 md:hover:decoration-zinc-500">
                            {{ __('messages.publish_request') }}
                        </a>
                    </li>

                    <li x-data="{ open: false }" @mouseenter="showOverlay=true" @mouseleave="showOverlay=false" class="z-30 flex flex-col items-start h-auto border-b border-zinc-800 md:h-full md:border-b-0 group md:flex-row md:items-center">
                        <a href="#_" x-on:click="open=!open" class="flex items-center w-full h-16 gap-1 text-sm font-semibold text-zinc-300 transition-all duration-200 hover:bg-white/5 md:hover:bg-transparent px-7 md:h-full md:px-0 md:w-auto hover:text-zinc-100 md:hover:underline md:hover:decoration-2 md:hover:underline-offset-4 md:hover:decoration-zinc-500">
                            <span class="">{{ __('messages.how_it_works') }}</span>
                            <svg :class="{ 'group-hover:-rotate-180' : !mobileMenuOpen, '-rotate-180' : mobileMenuOpen && open }" class="w-5 h-5 transition-all duration-300 ease-out" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" class=""></path></svg>
                        </a>
                        <div 
                            :class="{ 'hidden md:block opacity-0 invisible md:absolute' : !open, 'md:invisible md:opacity-0 md:hidden md:absolute' : open }"
                            class="top-0 left-0 w-screen space-y-3 transition-transform duration-300 ease-out bg-white border-t border-b border-zinc-200 md:shadow-2xl md:-translate-y-2 md:mt-24 md:block md:group-hover:block md:group-hover:visible md:group-hover:opacity-100 md:group-hover:translate-y-0" x-cloak>
                            <ul class="flex flex-col justify-between mx-auto max-w-7xl md:flex-row md:px-12">
                                <div class="flex flex-col w-full border-l border-r md:flex-row divide-x divide-zinc-100 border-zinc-100">
                                    <div class="w-auto divide-y divide-zinc-100">
                                        <a href="/{{ app()->getLocale() }}/content/our-mission" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100 group">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.our_mission') }}</span>
                                            <span class="block font-light leading-5 text-zinc-500">{{ __('messages.menu.our_mission_desc') }}</span>
                                        </a>
                                        <a href="/{{ app()->getLocale() }}/content/join_us" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100 group">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.join_us') }}</span>
                                            <span class="block leading-5 text-zinc-500">{{ __('messages.menu.join_us_desc') }}</span>
                                        </a>
                                        <a href="{{ route('pricing') }}" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.pricing') }}</span>
                                            <span class="block leading-5 text-zinc-500">{{ __('messages.menu.pricing_desc') }}</span>
                                        </a>
                                    </div>
                                    <div class="w-auto divide-y divide-zinc-100">
                                        <a href="/{{ app()->getLocale() }}/content/mediation" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.mediation') }}</span>
                                            <span class="block leading-5 text-zinc-500">{{ __('messages.menu.mediation_desc') }}</span>
                                        </a>
                                        <a href="/{{ app()->getLocale() }}/content/contract" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.terms') }}</span>
                                            <span class="block leading-5 text-zinc-500">{{ __('messages.menu.terms_desc') }}</span>
                                        </a>
                                    </div>
                                    <div class="w-auto divide-y divide-zinc-100">
                                        <a href="/{{ app()->getLocale() }}/content/buyer_guide" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.buyer_guide') }}</span>
                                            <span class="block font-light leading-5 text-zinc-500">{{ __('messages.menu.buyer_guide_desc') }}</span>
                                        </a>
                                        <a href="/{{ app()->getLocale() }}/content/seller-guide" class="block p-7 text-sm transition duration-200 hover:bg-neutral-100">
                                            <span class="block mb-1 font-medium text-black">{{ __('messages.menu.seller_guide') }}</span>
                                            <span class="block leading-5 text-zinc-500">{{ __('messages.menu.seller_guide_desc') }}</span>
                                        </a>
                                    </div>
                                </div>
                            </ul>
                        </div>
                    </li>



                    {{-- Language Switcher Mobile --}}
                    <li class="flex items-center justify-center w-full h-16 border-b border-zinc-800 md:hidden px-7">
                        <x-language-switcher />
                    </li>

                    @guest
                        <li class="relative z-30 flex flex-col items-center justify-center flex-shrink-0 w-full h-auto pt-3 space-y-3 text-sm md:hidden px-7">
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-white transition duration-200 border rounded-md border-zinc-600 bg-zinc-900 hover:bg-zinc-800 hover:border-zinc-500">
                                {{ __('messages.login') }}
                            </a>
                            <a href="{{ route('signup') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-black transition duration-200 bg-white rounded-md hover:bg-zinc-200">
                                {{ __('messages.register') }}
                            </a>
                        </li>
                    @else
                        <li class="flex items-center justify-center w-full pt-3 md:hidden px-7">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-black transition duration-200 bg-white rounded-md hover:bg-zinc-200">
                                {{ __('messages.dashboard') }}
                            </a>
                        </li>
                    @endguest

                </ul>
            </nav>
            
            {{-- Language Switcher --}}
            <div class="relative z-30 items-center justify-center flex-shrink-0 hidden h-full mr-3 md:flex">
                <x-language-switcher />
            </div>

            @guest
                <div class="relative z-30 items-center justify-center flex-shrink-0 hidden h-full space-x-3 text-sm md:flex">
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white transition duration-200 border rounded-md border-zinc-600 bg-zinc-900 hover:bg-zinc-800 hover:border-zinc-500">
                        {{ __('messages.login') }}
                    </a>
                    <a href="{{ route('signup') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-black transition duration-200 bg-white rounded-md hover:bg-zinc-200">
                        {{ __('messages.register') }}
                    </a>
                </div>
            @else
                <a href="{{ route('dashboard') }}" class="relative z-20 flex-shrink-0 hidden px-4 py-2 ml-2 text-sm font-semibold text-black transition duration-200 bg-white rounded-md md:inline-flex hover:bg-zinc-200">
                    {{ __('messages.dashboard') }}
                </a>
            @endguest

        </div>
    </x-container>

</header>
