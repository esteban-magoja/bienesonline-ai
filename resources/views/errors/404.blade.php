<x-layouts.marketing
    :seo="[
        'title' => __('messages.404_title') . ' - ' . setting('site.title', 'Raxta'),
        'description' => __('messages.404_message'),
        'type' => 'website',
    ]"
>
    <section class="flex items-center justify-center min-h-[calc(100vh-6rem)] px-6 py-20 sm:px-8">
        <div class="w-full max-w-2xl text-center">
            <p class="text-8xl font-bold tracking-tight text-zinc-200 sm:text-9xl">404</p>
            <h1 class="mt-6 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                {{ __('messages.404_title') }}
            </h1>
            <p class="mx-auto mt-5 max-w-xl text-base leading-7 text-zinc-600 sm:text-lg">
                {{ __('messages.404_message') }}.
            </p>
            <a
                href="{{ route_localized('home') }}"
                class="mt-8 inline-flex items-center justify-center rounded-md bg-black px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2"
            >
                {{ __('messages.go_home') }}
            </a>
        </div>
    </section>
</x-layouts.marketing>
