<section>
    <x-marketing.elements.heading
        level="h2"
        :title="__('messages.pricing.title')"
        :description="__('messages.pricing.subtitle')" 
    />

    <div x-data="{ on: false, billing: '{{ get_default_billing_cycle() }}',
            toggleRepositionMarker(toggleButton){
                if(this.$refs.marker && toggleButton){
                    this.$refs.marker.style.width=toggleButton.offsetWidth + 'px';
                    this.$refs.marker.style.height=toggleButton.offsetHeight + 'px';
                    this.$refs.marker.style.left=toggleButton.offsetLeft + 'px';
                }
            }
         }" 
        x-init="
                setTimeout(function(){ 
                    toggleRepositionMarker($refs.monthly); 
                    if($refs.marker){
                        $refs.marker.classList.remove('opacity-0');
                        setTimeout(function(){ 
                            $refs.marker.classList.add('duration-300', 'ease-out');
                        }, 10); 
                    }
                }, 1);
        "
        class="mx-auto mt-12 mb-2 w-full max-w-6xl md:my-12">

        @if(has_monthly_yearly_toggle())
            <div class="flex relative justify-start items-center pb-5 -translate-y-2 md:justify-center">
                <div class="inline-flex relative justify-center items-center p-1 w-auto text-center rounded-full border-2 -translate-y-3 md:mx-auto border-zinc-900">
                    <div x-ref="monthly" x-on:click="billing='Monthly'; toggleRepositionMarker($el)" :class="{ 'text-white': billing == 'Monthly', 'text-zinc-900' : billing != 'Monthly' }" class="relative z-20 px-3.5 py-1 text-sm font-medium leading-6 rounded-full duration-300 ease-out cursor-pointer">
                        {{ __('messages.pricing.monthly') }}
                    </div>
                    <div x-ref="yearly" x-on:click="billing='Yearly'; toggleRepositionMarker($el)" :class="{ 'text-white': billing == 'Yearly', 'text-zinc-900' : billing != 'Yearly' }" class="relative z-20 px-3.5 py-1 text-sm font-medium leading-6 rounded-full duration-300 ease-out cursor-pointer">
                        {{ __('messages.pricing.yearly') }}
                    </div>
                    <div x-ref="marker" class="absolute left-0 z-10 w-1/2 h-full opacity-0">
                        <div class="w-full h-full rounded-full shadow-sm bg-zinc-900"></div>
                    </div>
                </div>  
            </div>
        @endif

        <div class="flex flex-col flex-wrap lg:flex-row lg:space-x-5">

            @foreach(Wave\Plan::where('active', 1)->get() as $plan)
                @php $features = explode(',', $plan->features); @endphp
                <div
                    x-show="(billing == 'Monthly' && '{{ $plan->monthly_price_id }}' != '') || (billing == 'Yearly' && '{{ $plan->yearly_price_id }}' != '')" 
                    class="flex-1 px-0 mx-auto mb-6 w-full md:max-w-lg lg:mb-0">
                    <div class="flex flex-col lg:mb-10 h-full bg-white rounded-xl border-2  @if($plan->default){{ 'border-zinc-900 lg:scale-105' }}@else{{ 'border-zinc-200' }}@endif shadow-sm sm:mb-0">
                        <div class="px-8 pt-8">
                            <span class="px-4 py-1 text-base font-medium text-white rounded-full bg-zinc-900 text-uppercase" data-primary="indigo-700">
                                {{ $plan->name }}
                            </span>
                        </div>

                        <div class="px-8 mt-5">
                            <span class="text-5xl font-bold">$ <span x-text="billing == 'Monthly' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span></span>
                            <span class="text-xl font-bold text-zinc-500"><span x-text="billing == 'Monthly' ? '{{ __('messages.pricing.per_month') }}' : '{{ __('messages.pricing.per_year') }}'"></span></span>
                        </div>

                        <div class="px-8 pb-10 mt-3">
                            <p class="text-base leading-7 text-zinc-500">{{ $plan->description }}</p>
                        </div>

                        <div class="p-8 mt-auto rounded-b-lg bg-zinc-50">
                            <ul class="flex flex-col">
                                @foreach($features as $feature)
                                    <li class="mt-1">
                                        <span class="flex items-center text-green-500">
                                            <svg class="mr-3 w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M0 11l2-2 5 5L18 3l2 2L7 18z"></path></svg>
                                            <span class="text-zinc-700">
                                                {{ $feature }}
                                            </span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-8">
                                @auth
                                    <x-button class="w-full" tag="a" href="{{ url('/settings/subscription') }}">
                                        {{ __('messages.pricing.get_started') }}
                                    </x-button>
                                @else
                                    <x-button class="w-full" tag="a" href="{{ url('/signup') }}">
                                        {{ __('messages.pricing.get_started') }}
                                    </x-button>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <p class="mt-0 mb-8 w-full text-center text-zinc-500 sm:my-10"></p>
</section>