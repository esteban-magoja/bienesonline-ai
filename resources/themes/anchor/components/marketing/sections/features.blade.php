<section>
    <x-marketing.elements.heading
        level="h2"
        :title="__('messages.home_page.features_title')"
        :description="__('messages.home_page.features_description')" 
    />
    <div class="text-center">
        <div class="grid grid-cols-1 gap-x-6 gap-y-12 mt-12 text-center sm:grid-cols-2 lg:mt-16 lg:grid-cols-4 lg:gap-x-8 lg:gap-y-16">
            <div>
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-brain class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.feature_1_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.feature_1_desc') }}
                    </p>
                </div>
            </div>
            <div>
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-handshake class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.feature_2_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.feature_2_desc') }}
                    </p>
                </div>
            </div>
            <div>
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-lock-key class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.feature_3_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.feature_3_desc') }}
                    </p>
                </div>
            </div>
            <div>
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-globe-hemisphere-west class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.feature_4_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.feature_4_desc') }}
                    </p>
                </div>
            </div>
        </div>
    </div>



</section>
