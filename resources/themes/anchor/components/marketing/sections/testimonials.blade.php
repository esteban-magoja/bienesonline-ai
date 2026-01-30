<section class="w-full">
    <x-marketing.elements.heading level="h2" :title="__('messages.home_page.testimonials_title')" :description="__('messages.home_page.testimonials_description')" />
    <ul role="list" class="grid grid-cols-1 gap-12 py-12 mx-auto max-w-2xl lg:max-w-none lg:grid-cols-3">
        <li>
            <figure class="flex flex-col justify-between h-full">
                <blockquote class="">
                    <p class="text-sm sm:text-base font-medium text-zinc-500">
                        {{ __('messages.home_page.testimonial_1_quote') }}
                    </p>
                </blockquote>
                <figcaption class="flex flex-col justify-between mt-6">
                    <img alt="Laura Gómez" src="https://randomuser.me/api/portraits/women/44.jpg" class="object-cover rounded-full grayscale size-14">
                    <div class="mt-4">
                        <div class="font-medium text-zinc-900">{{ __('messages.home_page.testimonial_1_name') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ __('messages.home_page.testimonial_1_role') }}
                        </div>
                    </div>
                </figcaption>
            </figure>
        </li>
        <li>
            <figure class="flex flex-col justify-between h-full">
                <blockquote class="">
                    <p class="text-sm sm:text-base font-medium text-zinc-500">
                        {{ __('messages.home_page.testimonial_2_quote') }}
                    </p>
                </blockquote>
                <figcaption class="flex flex-col justify-between mt-6">
                    <img alt="Carlos Fernández" src="https://randomuser.me/api/portraits/men/32.jpg" class="object-cover rounded-full grayscale size-14">
                    <div class="mt-4">
                        <div class="font-medium text-zinc-900">{{ __('messages.home_page.testimonial_2_name') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ __('messages.home_page.testimonial_2_role') }}
                        </div>
                    </div>
                </figcaption>
            </figure>
        </li>
        <li>
            <figure class="flex flex-col justify-between h-full">
                <blockquote class="">
                    <p class="text-sm sm:text-base font-medium text-zinc-500">
                        {{ __('messages.home_page.testimonial_3_quote') }}
                    </p>
                </blockquote>
                <figcaption class="flex flex-col justify-between mt-6">
                    <img alt="Sofía Rodríguez" src="https://randomuser.me/api/portraits/women/47.jpg" class="object-cover rounded-full grayscale size-14">
                    <div class="mt-4">
                        <div class="font-medium text-zinc-900">{{ __('messages.home_page.testimonial_3_name') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ __('messages.home_page.testimonial_3_role') }}
                        </div>
                    </div>
                </figcaption>
            </figure>
        </li>
    </ul>
</section>