
<section>
    <x-marketing.elements.heading
        level="h2"
        :title="__('messages.home_page.guides_title')"
        description="" 
    />
    <div class="text-center max-w-3xl mx-auto">
        <div class="grid grid-cols-1 gap-x-6 gap-y-12 mt-12 text-center sm:grid-cols-2 lg:mt-16 lg:grid-cols-2 lg:gap-x-8 lg:gap-y-16">
            <div><a href="/{{ app()->getLocale() }}/content/buyer_guide">
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-note class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.buyer_guide_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.buyer_guide_desc') }}
                    </p>
                </div></a>
            </div>
            <div><a href="/{{ app()->getLocale() }}/content/seller-guide">
                <div class="flex justify-center items-center mx-auto bg-zinc-100 rounded-full size-12">
                    <x-phosphor-note-pencil class="w-6 h-6" />
                </div>
                <div class="mt-6">
                    <h3 class="font-medium text-zinc-900">{{ __('messages.home_page.seller_guide_title') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ __('messages.home_page.seller_guide_desc') }}
                    </p>
                </div></a>
            </div>
        </div>
    </div>



</section>