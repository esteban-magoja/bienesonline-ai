<section class="w-full py-16">
    <x-marketing.elements.heading level="h2" :title="__('messages.home_page.stats_title')" :description="__('messages.home_page.stats_description')" />
    <dl class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">

        {{-- +25 años --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-blue-50">
                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <dt class="text-4xl font-bold text-zinc-900">+25</dt>
            <dd class="text-sm font-medium text-zinc-500">{{ __('messages.home_page.stat_1') }}</dd>
        </div>

        {{-- +16M propiedades --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-green-50">
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15v-6h-6v6H3.75A.75.75 0 013 21V9.75z"/>
                </svg>
            </div>
            <dt class="text-4xl font-bold text-zinc-900">+16M</dt>
            <dd class="text-sm font-medium text-zinc-500">{{ __('messages.home_page.stat_2') }}</dd>
        </div>

        {{-- +53K agentes --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-purple-50">
                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-5-3.874M9 20H4v-2a4 4 0 015-3.874m6-4.126a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0zM3 13a3 3 0 110-6 3 3 0 010 6z"/>
                </svg>
            </div>
            <dt class="text-4xl font-bold text-zinc-900">+53K</dt>
            <dd class="text-sm font-medium text-zinc-500">{{ __('messages.home_page.stat_3') }}</dd>
        </div>

        {{-- 600K visitas --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-orange-50">
                <svg class="w-7 h-7 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.5l4-4 4 4 4-5 4 4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 20h18"/>
                </svg>
            </div>
            <dt class="text-4xl font-bold text-zinc-900">600K</dt>
            <dd class="text-sm font-medium text-zinc-500">{{ __('messages.home_page.stat_4') }}</dd>
        </div>

    </dl>
</section>